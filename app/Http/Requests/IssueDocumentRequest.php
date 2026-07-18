<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('documents.issue') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_type_id' => [
                'required',
                'uuid',
                Rule::exists('document_types', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'purpose' => trim((string) $this->input('purpose')),
        ]);
    }
}
