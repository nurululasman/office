<?php

namespace App\Http\Requests;

use App\Models\DocumentTemplate;
use App\Services\DocumentTemplates\QuotationItemPresentation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $template = $this->route('document_template');

        return $template instanceof DocumentTemplate
            ? $this->user()?->can('update', $template) === true
            : $this->user()?->can('create', DocumentTemplate::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var DocumentTemplate|null $template */
        $template = $this->route('document_template');

        return [
            'company_profile_id' => [
                'required', 'uuid',
                Rule::exists('company_profiles', 'id')->where(function ($query) use ($template): void {
                    $query->where('is_active', true);
                    if ($template) {
                        $query->orWhere('id', $template->company_profile_id);
                    }
                }),
            ],
            'template_key' => [
                'required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9]*(?:-[a-z0-9]+)*\z/',
                $template
                    ? Rule::in([$template->template_key])
                    : Rule::unique('document_templates', 'template_key')->where('type', 'quotation'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'content_html' => ['required', 'string', 'max:200000'],
            'item_schema_json' => ['required', 'string', 'max:100000'],
            'default_intro_text' => ['nullable', 'string', 'max:10000'],
            'default_closing_text' => ['nullable', 'string', 'max:10000'],
            'default_terms_text' => ['nullable', 'string', 'max:50000'],
            'lock_version' => [$template ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $schema = json_decode((string) $this->input('item_schema_json'), true);
            if (! is_array($schema)) {
                $validator->errors()->add('item_schema_json', 'Item schema harus berupa JSON object yang valid.');

                return;
            }
            if (! is_array($schema['columns'] ?? null)) {
                $validator->errors()->add('item_schema_json', 'Item schema harus memiliki array columns.');

                return;
            }

            try {
                app(QuotationItemPresentation::class)->resolve($schema);
            } catch (\LogicException $exception) {
                $validator->errors()->add('item_schema_json', $exception->getMessage());
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'template_key' => strtolower(trim((string) $this->input('template_key'))),
            'name' => trim((string) $this->input('name')),
            'content_html' => trim((string) $this->input('content_html')),
            'default_intro_text' => $this->nullableTrimmed('default_intro_text'),
            'default_closing_text' => $this->nullableTrimmed('default_closing_text'),
        ]);
    }

    /** @return array<string, mixed> */
    public function templateData(): array
    {
        $validated = $this->validated();
        $terms = preg_split('/\R/u', (string) ($validated['default_terms_text'] ?? '')) ?: [];

        return [
            'company_profile_id' => $validated['company_profile_id'],
            'template_key' => $validated['template_key'],
            'name' => $validated['name'],
            'content_html' => $validated['content_html'],
            'item_schema' => json_decode($validated['item_schema_json'], true, flags: JSON_THROW_ON_ERROR),
            'default_intro_text' => $validated['default_intro_text'] ?? null,
            'default_closing_text' => $validated['default_closing_text'] ?? null,
            'default_terms' => array_values(array_filter(array_map('trim', $terms), fn (string $term): bool => $term !== '')),
            'editor_config' => [],
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
