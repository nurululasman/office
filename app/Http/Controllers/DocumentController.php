<?php

namespace App\Http\Controllers;

use App\Exceptions\DocumentIssuanceException;
use App\Http\Requests\IssueDocumentRequest;
use App\Http\Requests\VoidDocumentRequest;
use App\Models\Document;
use App\Models\DocumentType;
use App\Services\Documents\DocumentNumberIssuer;
use App\Services\Documents\DocumentVoidService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Document::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'document_type_id' => ['nullable', 'uuid', 'exists:document_types,id'],
            'period_year' => ['nullable', 'integer', 'between:1,9999'],
            'status' => ['nullable', 'in:issued,void'],
        ]);

        $documents = Document::query()
            ->with(['documentType', 'issuer', 'voider'])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereLike('number', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('title', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('purpose', "%{$search}%", caseSensitive: false);
                });
            })
            ->when($filters['document_type_id'] ?? null, fn ($query, string $id) => $query->where('document_type_id', $id))
            ->when($filters['period_year'] ?? null, fn ($query, int $year) => $query->where('period_year', $year))
            ->when(($filters['status'] ?? null) === 'issued', fn ($query) => $query->whereNull('voided_at'))
            ->when(($filters['status'] ?? null) === 'void', fn ($query) => $query->whereNotNull('voided_at'))
            ->latest('issued_at')
            ->paginate(20)
            ->withQueryString();

        return view('documents.index', [
            'documents' => $documents,
            'documentTypes' => DocumentType::query()->orderBy('name')->get(['id', 'code', 'name']),
            'periodYears' => Document::query()->distinct()->orderByDesc('period_year')->pluck('period_year'),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Document::class);

        return view('documents.create', [
            'documentTypes' => DocumentType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'number_pattern']),
        ]);
    }

    public function store(IssueDocumentRequest $request, DocumentNumberIssuer $issuer): RedirectResponse
    {
        $validated = $request->validated();
        $documentType = DocumentType::query()->findOrFail($validated['document_type_id']);

        try {
            $document = $issuer->issue(
                $documentType,
                $request->user(),
                $validated['title'],
                $validated['purpose'],
            );
        } catch (DocumentIssuanceException $exception) {
            throw ValidationException::withMessages(['document_type_id' => $exception->getMessage()]);
        }

        return redirect()->route('documents.issued', $document);
    }

    public function issued(Document $document): View
    {
        Gate::authorize('view', $document);

        return view('documents.issued', [
            'document' => $document->load(['documentType', 'issuer']),
        ]);
    }

    public function show(Document $document): View
    {
        Gate::authorize('view', $document);

        return view('documents.show', [
            'document' => $document->load(['documentType', 'issuer', 'voider']),
            'audits' => $document->audits()->with('actor')->oldest('occurred_at')->get(),
        ]);
    }

    public function void(VoidDocumentRequest $request, Document $document, DocumentVoidService $voider): RedirectResponse
    {
        $voider->void($document, $request->user(), $request->validated('reason'), $request);

        return redirect()->route('documents.show', $document)->with('status', 'Nomor dokumen berhasil di-void dan tetap tercatat di register.');
    }
}
