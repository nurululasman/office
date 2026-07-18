<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentTypeRequest;
use App\Http\Requests\UpdateDocumentTypeRequest;
use App\Models\DocumentType;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentNumberPattern;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', DocumentType::class);

        return view('document-types.index', [
            'documentTypes' => DocumentType::query()->latest()->paginate(15),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', DocumentType::class);

        return view('document-types.create');
    }

    public function store(
        StoreDocumentTypeRequest $request,
        DocumentNumberPattern $patterns,
        AuditLogger $audit,
    ): RedirectResponse {
        $documentType = DocumentType::query()->create($request->documentTypeData($patterns));
        $audit->record(
            'document_type.created',
            actor: $request->user(),
            subject: $documentType,
            after: $documentType->only($this->auditedFields()),
            request: $request,
        );

        return redirect()->route('document-types.index')->with('status', 'Tipe dokumen berhasil dibuat.');
    }

    public function edit(DocumentType $documentType, DocumentNumberPattern $patterns): View
    {
        Gate::authorize('update', $documentType);

        return view('document-types.edit', [
            'documentType' => $documentType,
            'segments' => $patterns->fromPattern($documentType->number_pattern),
        ]);
    }

    public function update(
        UpdateDocumentTypeRequest $request,
        DocumentType $documentType,
        DocumentNumberPattern $patterns,
        AuditLogger $audit,
    ): RedirectResponse {
        $before = $documentType->only($this->auditedFields());
        $documentType->update($request->documentTypeData($patterns));
        $audit->record(
            'document_type.updated',
            actor: $request->user(),
            subject: $documentType,
            before: $before,
            after: $documentType->only($this->auditedFields()),
            request: $request,
        );

        return redirect()->route('document-types.index')->with('status', 'Tipe dokumen berhasil diperbarui.');
    }

    public function toggle(Request $request, DocumentType $documentType, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('update', $documentType);
        $before = $documentType->only($this->auditedFields());
        $documentType->update(['is_active' => ! $documentType->is_active]);
        $audit->record(
            $documentType->is_active ? 'document_type.activated' : 'document_type.deactivated',
            actor: $request->user(),
            subject: $documentType,
            before: $before,
            after: $documentType->only($this->auditedFields()),
            request: $request,
        );

        return back()->with('status', $documentType->is_active ? 'Tipe dokumen diaktifkan.' : 'Tipe dokumen dinonaktifkan.');
    }

    public function destroy(Request $request, DocumentType $documentType, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('delete', $documentType);

        if ($documentType->documents()->exists() || $documentType->sequences()->exists()) {
            return back()->withErrors('Tipe dokumen yang sudah memiliki register atau sequence tidak dapat dihapus. Nonaktifkan tipe tersebut.');
        }

        $before = $documentType->only($this->auditedFields());
        $subjectId = $documentType->getKey();
        $documentType->delete();
        $audit->record(
            'document_type.deleted',
            actor: $request->user(),
            before: $before,
            context: ['document_type_id' => $subjectId],
            request: $request,
        );

        return redirect()->route('document-types.index')->with('status', 'Tipe dokumen yang belum digunakan berhasil dihapus.');
    }

    public function preview(Request $request, DocumentNumberPattern $patterns): JsonResponse
    {
        Gate::authorize('create', DocumentType::class);
        $validated = $request->validate(['segments' => ['required', 'array', 'min:1', 'max:30']]);
        $segments = $patterns->validateSegments($validated['segments']);

        return response()->json([
            'pattern' => $patterns->toPattern($segments),
            'preview' => $patterns->preview($segments, now(config('office.business_timezone')), 1),
        ]);
    }

    /** @return list<string> */
    private function auditedFields(): array
    {
        return ['code', 'name', 'number_pattern', 'reset_period', 'approval_mode', 'is_active'];
    }
}
