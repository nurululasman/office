<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentTemplateRequest;
use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Services\DocumentTemplates\DocumentTemplateLifecycle;
use App\Services\DocumentTemplates\DocumentTemplatePreviewFactory;
use App\Services\Quotations\QuotationDocumentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use LogicException;

class DocumentTemplateController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', DocumentTemplate::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,active,archived'],
        ]);

        return view('quotation-templates.index', [
            'templates' => DocumentTemplate::query()
                ->with(['companyProfile', 'creator', 'activator'])
                ->where('type', 'quotation')
                ->when($filters['q'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                    $query->whereLike('name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('template_key', "%{$search}%", caseSensitive: false);
                }))
                ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->orderBy('template_key')
                ->orderByDesc('version')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', DocumentTemplate::class);

        return view('quotation-templates.form', [
            'template' => new DocumentTemplate([
                'content_html' => DocumentTemplate::LEGACY_CONTENT_HTML,
                'item_schema' => ['columns' => []],
            ]),
            'companyProfiles' => $this->companyProfiles(),
        ]);
    }

    public function store(DocumentTemplateRequest $request, DocumentTemplateLifecycle $lifecycle): RedirectResponse
    {
        $template = $this->runLifecycle(fn () => $lifecycle->createDraft(
            $request->templateData() + ['version' => 1],
            $request->user(),
            $request,
        ));

        return redirect()->route('quotation-templates.show', $template)
            ->with('status', 'Draft template quotation berhasil dibuat.');
    }

    public function show(
        DocumentTemplate $documentTemplate,
        DocumentTemplatePreviewFactory $previewFactory,
        QuotationDocumentRenderer $renderer,
    ): View {
        Gate::authorize('view', $documentTemplate);
        $documentTemplate->load(['companyProfile', 'creator', 'updater', 'activator']);

        try {
            $quotation = $previewFactory->make($documentTemplate);
            $previewHtml = $renderer->content($quotation, true, false);
            $previewError = null;
        } catch (LogicException|\RuntimeException $exception) {
            $previewHtml = null;
            $previewError = $exception->getMessage();
        }

        return view('quotation-templates.show', [
            'template' => $documentTemplate,
            'previewHtml' => $previewHtml,
            'previewError' => $previewError,
        ]);
    }

    public function preview(
        DocumentTemplate $documentTemplate,
        DocumentTemplatePreviewFactory $previewFactory,
        QuotationDocumentRenderer $renderer,
    ): Response {
        Gate::authorize('view', $documentTemplate);

        try {
            $quotation = $previewFactory->make($documentTemplate);

            return response()->view('quotations.document', [
                'quotation' => $quotation,
                'renderedHtml' => $renderer->content($quotation, true, false),
                'isDraft' => true,
                'browserPreview' => false,
            ]);
        } catch (LogicException|\RuntimeException $exception) {
            return response()->view('quotation-templates.preview-error', [
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function edit(DocumentTemplate $documentTemplate): View
    {
        Gate::authorize('update', $documentTemplate);

        return view('quotation-templates.form', [
            'template' => $documentTemplate,
            'companyProfiles' => $this->companyProfiles($documentTemplate->company_profile_id),
        ]);
    }

    public function update(
        DocumentTemplateRequest $request,
        DocumentTemplate $documentTemplate,
        DocumentTemplateLifecycle $lifecycle,
    ): RedirectResponse {
        $template = $this->runLifecycle(fn () => $lifecycle->updateDraft(
            $documentTemplate,
            $request->templateData(),
            $request->integer('lock_version'),
            $request->user(),
            $request,
        ));

        return redirect()->route('quotation-templates.show', $template)
            ->with('status', 'Draft template quotation berhasil diperbarui.');
    }

    public function duplicate(
        Request $request,
        DocumentTemplate $documentTemplate,
        DocumentTemplateLifecycle $lifecycle,
    ): RedirectResponse {
        $template = $this->runLifecycle(fn () => $lifecycle->createVersion($documentTemplate, $request->user(), $request));

        return redirect()->route('quotation-templates.edit', $template)
            ->with('status', 'Versi draft baru berhasil dibuat.');
    }

    public function activate(
        Request $request,
        DocumentTemplate $documentTemplate,
        DocumentTemplateLifecycle $lifecycle,
    ): RedirectResponse {
        Gate::authorize('activate', $documentTemplate);
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $template = $this->runLifecycle(fn () => $lifecycle->activate(
            $documentTemplate,
            (int) $validated['lock_version'],
            $request->user(),
            $request,
        ));

        return redirect()->route('quotation-templates.show', $template)
            ->with('status', 'Template quotation berhasil diaktifkan.');
    }

    public function archive(
        Request $request,
        DocumentTemplate $documentTemplate,
        DocumentTemplateLifecycle $lifecycle,
    ): RedirectResponse {
        Gate::authorize('archive', $documentTemplate);
        $validated = $request->validate(['lock_version' => ['required', 'integer', 'min:0']]);
        $template = $this->runLifecycle(fn () => $lifecycle->archive(
            $documentTemplate,
            (int) $validated['lock_version'],
            $request->user(),
            $request,
        ));

        return redirect()->route('quotation-templates.show', $template)
            ->with('status', 'Template quotation berhasil diarsipkan.');
    }

    private function companyProfiles(?string $includeId = null)
    {
        return CompanyProfile::query()
            ->where(fn ($query) => $query->where('is_active', true)
                ->when($includeId, fn ($query) => $query->orWhere('id', $includeId)))
            ->orderBy('display_name')
            ->get();
    }

    private function runLifecycle(callable $action): DocumentTemplate
    {
        try {
            return $action();
        } catch (LogicException $exception) {
            throw ValidationException::withMessages(['template' => $exception->getMessage()]);
        }
    }
}
