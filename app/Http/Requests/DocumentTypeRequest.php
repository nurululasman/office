<?php

namespace App\Http\Requests;

use App\Models\DocumentType;
use App\Services\Documents\DocumentNumberPattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class DocumentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('document-types.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var DocumentType|null $documentType */
        $documentType = $this->route('document_type');

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/\A[A-Z][A-Z0-9_-]*\z/', Rule::unique('document_types')->ignore($documentType)],
            'name' => ['required', 'string', 'max:150'],
            'approval_mode' => ['required', Rule::in(['direct', 'maker_checker'])],
            'latest_sequence' => ['required', 'integer', 'min:0'],
            'segments' => ['required', 'array', 'min:1', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $segments = $this->input('segments');
        if (is_string($segments)) {
            $segments = json_decode($segments, true);
        }

        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'segments' => $segments,
        ]);
    }

    /** @return array<string, mixed> */
    public function documentTypeData(DocumentNumberPattern $patterns): array
    {
        $validated = $this->validated();
        $segments = $patterns->validateSegments($validated['segments']);
        /** @var DocumentType|null $documentType */
        $documentType = $this->route('document_type');

        return [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'number_pattern' => $patterns->toPattern($segments),
            'reset_period' => 'yearly',
            'approval_mode' => $validated['approval_mode'],
            'is_active' => $validated['is_active'] ?? $documentType?->is_active ?? true,
        ];
    }
}
