<?php

namespace App\Http\Requests;

use App\Models\DocumentTemplate;
use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class QuotationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation instanceof Quotation
            ? $this->user()?->can('update', $quotation) === true
            : $this->user()?->can('create', Quotation::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'template_id' => ['required', 'uuid', Rule::exists('document_templates', 'id')->where(function ($query): void {
                $query->where('type', 'quotation')->where(function ($query): void {
                    $query->where('is_active', true);
                    if ($this->route('quotation') instanceof Quotation) {
                        $query->orWhere('id', $this->route('quotation')->template_id);
                    }
                });
            })],
            'quotation_date' => ['required', 'date_format:Y-m-d'],
            'subject' => ['required', 'string', 'max:255'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_address' => ['required', 'string', 'max:5000'],
            'attention_name' => ['nullable', 'string', 'max:255'],
            'attention_role' => ['nullable', 'string', 'max:255'],
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_title' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'intro_text' => ['nullable', 'string', 'max:10000'],
            'closing_text' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.values' => ['required', 'array'],
            'items.*.values.*' => ['nullable', 'string', 'max:10000'],
            'terms' => ['nullable', 'array', 'max:50'],
            'terms.*' => ['nullable', 'string', 'max:5000'],
            'lock_version' => [$this->route('quotation') instanceof Quotation ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $template = DocumentTemplate::query()->find($this->input('template_id'));
            $quotation = $this->route('quotation');
            $schema = $quotation instanceof Quotation && $quotation->template_id === $this->input('template_id')
                ? $quotation->item_schema
                : $template?->settings;
            $columns = is_array($schema['columns'] ?? null) ? $schema['columns'] : [];

            if ($template && $columns === []) {
                $validator->errors()->add('template_id', 'Template quotation belum memiliki definisi kolom.');

                return;
            }

            foreach ((array) $this->input('items', []) as $itemIndex => $item) {
                $values = is_array($item['values'] ?? null) ? $item['values'] : [];
                $allowedKeys = [];

                foreach ($columns as $column) {
                    if (! is_array($column) || ! is_string($column['key'] ?? null)) {
                        $validator->errors()->add('template_id', 'Definisi kolom template tidak valid.');

                        continue;
                    }

                    $key = $column['key'];
                    $allowedKeys[] = $key;
                    $value = $values[$key] ?? null;
                    $attribute = "items.{$itemIndex}.values.{$key}";

                    if (($column['required'] ?? false) && ($value === null || trim((string) $value) === '')) {
                        $validator->errors()->add($attribute, "Kolom {$column['label']} wajib diisi.");
                    }

                    if ($value !== null && trim((string) $value) !== '' && ! $this->validTypedValue((string) $value, (string) ($column['value_type'] ?? 'text'))) {
                        $validator->errors()->add($attribute, "Nilai {$column['label']} tidak sesuai tipe {$column['value_type']}.");
                    }
                }

                foreach (array_diff(array_keys($values), $allowedKeys) as $unknownKey) {
                    $validator->errors()->add("items.{$itemIndex}.values.{$unknownKey}", 'Kolom tidak terdaftar pada template quotation.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $fields = ['subject', 'customer_name', 'customer_address', 'attention_name', 'attention_role', 'sender_name', 'sender_title', 'intro_text', 'closing_text'];
        $normalized = [];

        foreach ($fields as $field) {
            $normalized[$field] = is_string($this->input($field)) ? trim($this->input($field)) : $this->input($field);
        }

        $normalized['currency'] = strtoupper(trim((string) $this->input('currency', 'IDR')));
        $this->merge($normalized);
    }

    private function validTypedValue(string $value, string $type): bool
    {
        return match ($type) {
            'decimal', 'currency' => preg_match('/^-?\d+(\.\d+)?$/', $value) === 1,
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && date_create_from_format('!Y-m-d', $value)?->format('Y-m-d') === $value,
            'boolean' => in_array($value, ['0', '1', 'true', 'false'], true),
            'text' => true,
            default => false,
        };
    }
}
