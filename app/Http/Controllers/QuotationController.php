<?php

namespace App\Http\Controllers;

use App\Exceptions\QuotationWorkflowException;
use App\Http\Requests\QuotationDraftRequest;
use App\Models\DocumentTemplate;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\DocumentTemplates\DocumentTemplateHtmlSanitizer;
use App\Services\Quotations\QuotationDocumentRenderer;
use App\Services\Quotations\QuotationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Quotation::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,pending_approval,rejected,complete,void'],
        ]);

        $quotations = Quotation::query()
            ->with(['creator', 'document'])
            ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->whereLike('subject', "%{$search}%", caseSensitive: false)
                    ->orWhereLike('customer_name', "%{$search}%", caseSensitive: false);
            }))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest('quotation_date')->latest()->paginate(20)->withQueryString();

        return view('quotations.index', compact('quotations', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('create', Quotation::class);

        return view('quotations.form', [
            'quotation' => new Quotation(['quotation_date' => now(config('office.business_timezone'))->toDateString(), 'currency' => 'IDR']),
            'templates' => $this->templates(),
            'selectedTemplateId' => null,
            'senders' => User::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(QuotationDraftRequest $request, AuditLogger $audit): RedirectResponse
    {
        $quotation = DB::transaction(function () use ($request, $audit): Quotation {
            $quotation = new Quotation;
            $this->persist($quotation, $request->validated(), $request->user()->getKey());
            $audit->record('quotation.created', $request->user(), $quotation, after: $quotation->toArray(), request: $request);

            return $quotation;
        });

        return redirect()->route(
            $request->validated('submit_action') === 'preview' ? 'quotations.preview' : 'quotations.show',
            $quotation
        )->with('status', 'Draft quotation berhasil dibuat.');
    }

    public function show(Quotation $quotation): View
    {
        Gate::authorize('view', $quotation);

        return view('quotations.show', [
            'quotation' => $quotation->load(['creator', 'sender', 'document.voider', 'terms', 'submitter', 'approver', 'rejecter', 'completer', 'generatedFiles']),
            'audits' => $quotation->audits()->with('actor')->oldest('occurred_at')->get(),
        ]);
    }

    public function edit(Quotation $quotation): View
    {
        Gate::authorize('update', $quotation);

        $templates = $this->templates();
        if (! $templates->contains('id', $quotation->template_id)) {
            $templates->push($quotation->template);
        }

        $senders = User::query()->where('is_active', true)->orderBy('name')->get();
        if ($quotation->sender_id && ! $senders->contains('id', $quotation->sender_id)) {
            $sender = User::find($quotation->sender_id);
            if ($sender) {
                $senders->push($sender);
            }
        }

        return view('quotations.form', [
            'quotation' => $quotation->load(['terms']),
            'templates' => $templates,
            'selectedTemplateId' => $quotation->template_id,
            'senders' => $senders,
        ]);
    }

    public function preview(Quotation $quotation, QuotationDocumentRenderer $renderer): View
    {
        Gate::authorize('preview', $quotation);

        $isDraft = ! in_array($quotation->status, ['complete', 'void'], true);

        return view('quotations.document', [
            'quotation' => $quotation,
            'renderedHtml' => $renderer->content($quotation, $isDraft),
            'isDraft' => $isDraft,
            'browserPreview' => true,
        ]);
    }

    public function previewPdf(Quotation $quotation): StreamedResponse
    {
        return $this->pdfResponse($quotation, 'inline');
    }

    public function downloadPdf(Quotation $quotation): StreamedResponse
    {
        return $this->pdfResponse($quotation, 'attachment');
    }

    public function update(QuotationDraftRequest $request, Quotation $quotation, AuditLogger $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $quotation, $audit): void {
            $locked = Quotation::query()->lockForUpdate()->findOrFail($quotation->getKey());
            if ($locked->lock_version !== (int) $request->validated('lock_version') || ! in_array($locked->status, ['draft', 'rejected'], true)) {
                throw ValidationException::withMessages(['lock_version' => 'Draft telah berubah atau tidak lagi dapat diedit. Muat ulang halaman.']);
            }
            $wasRejected = $locked->status === 'rejected';
            $before = $locked->load(['terms'])->toArray();
            $this->persist($locked, $request->validated(), $locked->created_by);
            if ($wasRejected) {
                $locked->status = 'draft';
                $locked->save();
            }
            $locked->increment('lock_version');
            $audit->record($wasRejected ? 'quotation.revised' : 'quotation.updated', $request->user(), $locked, before: $before, after: $locked->load(['terms'])->toArray(), request: $request);
        });

        return redirect()->route(
            $request->validated('submit_action') === 'preview' ? 'quotations.preview' : 'quotations.show',
            $quotation
        )->with('status', 'Draft quotation berhasil diperbarui.');
    }

    public function complete(Quotation $quotation, Request $request, QuotationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('completeDirect', $quotation);

        return $this->runWorkflow(fn () => $workflow->completeDirect($quotation, $request->user(), $request), $quotation);
    }

    public function submit(Quotation $quotation, Request $request, QuotationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('submit', $quotation);

        return $this->runWorkflow(fn () => $workflow->submit($quotation, $request->user(), $request), $quotation);
    }

    public function approve(Quotation $quotation, Request $request, QuotationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('approve', $quotation);

        return $this->runWorkflow(fn () => $workflow->approve($quotation, $request->user(), $request), $quotation);
    }

    public function reject(Quotation $quotation, Request $request, QuotationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('reject', $quotation);
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'min:10', 'max:5000']]);

        return $this->runWorkflow(fn () => $workflow->reject($quotation, $request->user(), $validated['reason'], $request), $quotation);
    }

    public function void(Quotation $quotation, Request $request, QuotationWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('void', $quotation);
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'min:5', 'max:2000']]);

        return $this->runWorkflow(fn () => $workflow->void($quotation, $request->user(), trim($validated['reason']), $request), $quotation);
    }

    /** @param array<string, mixed> $data */
    private function persist(Quotation $quotation, array $data, int $creatorId): void
    {
        $template = DocumentTemplate::query()->findOrFail($data['template_id']);
        $keepsTemplate = $quotation->exists && $quotation->template_id === $template->getKey();
        $contentHtml = app(DocumentTemplateHtmlSanitizer::class)->sanitize((string) $data['content_html']);
        $termsHtml = app(DocumentTemplateHtmlSanitizer::class)->sanitize((string) $data['terms_html']);

        $senderId = ! empty($data['sender_id']) ? (int) $data['sender_id'] : $creatorId;
        $isSelfSender = $senderId === $creatorId;
        $defaultApprovalMode = $this->approvalMode();
        $approvalMode = ! $isSelfSender ? 'maker_checker' : $defaultApprovalMode;

        $sender = User::query()->find($senderId) ?? User::query()->find($creatorId);
        $senderName = ($sender && ! empty($sender->name)) ? $sender->name : ($data['sender_name'] ?? '');

        $fillData = array_merge(
            collect($data)->except(['content_html', 'terms_html', 'lock_version', 'submit_action'])->all(),
            [
                'created_by' => $creatorId,
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'approval_mode' => $approvalMode,
                'template_snapshot' => $keepsTemplate ? $quotation->template_snapshot : $template->loadMissing('companyProfile')->snapshot(),
                'content_html' => $contentHtml,
                'content_sha256' => hash('sha256', $contentHtml),
                'terms_html' => $termsHtml,
                'terms_sha256' => hash('sha256', $termsHtml),
                'template_content_sha256' => $keepsTemplate ? $quotation->template_content_sha256 : $template->content_sha256,
                'placeholder_contract_version' => DocumentTemplate::PLACEHOLDER_CONTRACT_VERSION,
            ]
        );

        $quotation->fill($fillData)->save();
    }

    private function approvalMode(): string
    {
        return (string) DB::table('document_types')->where('code', 'QUOTATION')->where('is_active', true)->value('approval_mode') ?: 'direct';
    }

    private function templates()
    {
        return DocumentTemplate::query()->with('companyProfile')
            ->where('type', 'quotation')->where('status', 'active')
            ->orderBy('name')->orderByDesc('version')->get();
    }

    private function runWorkflow(callable $action, Quotation $quotation): RedirectResponse
    {
        try {
            $result = $action();
        } catch (QuotationWorkflowException $exception) {
            throw ValidationException::withMessages(['workflow' => $exception->getMessage()]);
        }

        return redirect()->route('quotations.show', $result ?? $quotation)->with('status', 'Workflow quotation berhasil diproses.');
    }

    private function pdfResponse(Quotation $quotation, string $disposition): StreamedResponse
    {
        Gate::authorize('viewPdf', $quotation);
        $file = $quotation->generatedFiles()->where('kind', 'quotation_pdf')->first();
        if (! $file) {
            abort(404, 'PDF quotation belum tersedia.');
        }
        if ($file->status !== 'ready') {
            abort($file->status === 'failed' ? 503 : 409, $file->status === 'failed'
                ? 'Pembuatan PDF quotation gagal. Hubungi administrator untuk retry.'
                : 'PDF quotation sedang dibuat. Coba kembali beberapa saat lagi.');
        }

        $disk = Storage::disk($file->disk);
        if (! $disk->exists($file->path)) {
            abort(404, 'File PDF quotation tidak ditemukan.');
        }

        return $disk->response($file->path, $this->pdfFilename($quotation), [
            'Content-Type' => $file->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'ETag' => '"'.$file->sha256.'"',
        ], $disposition);
    }

    private function pdfFilename(Quotation $quotation): string
    {
        $number = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $quotation->document?->number);
        $number = trim((string) $number, '-.');

        return 'Quotation-'.($number !== '' ? $number : $quotation->getKey()).'.pdf';
    }
}
