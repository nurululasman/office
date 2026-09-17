<?php

namespace App\Http\Requests;

use App\Models\DocumentTemplate;
use App\Models\Quotation;
use App\Models\User;
use App\Services\DocumentTemplates\DocumentTemplateHtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

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
            'customer_address' => ['nullable', 'string', 'max:5000'],
            'attention_name' => ['nullable', 'string', 'max:255'],
            'attention_role' => ['nullable', 'string', 'max:255'],
            'sender_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_title' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'intro_text' => ['nullable', 'string', 'max:10000'],
            'closing_text' => ['nullable', 'string', 'max:10000'],
            'content_html' => ['required', 'string', 'max:'.DocumentTemplateHtmlSanitizer::MAX_HTML_BYTES],
            'terms_html' => ['required', 'string', 'max:'.DocumentTemplateHtmlSanitizer::MAX_HTML_BYTES],
            'submit_action' => ['nullable', Rule::in(['save', 'preview'])],
            'lock_version' => [$this->route('quotation') instanceof Quotation ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            try {
                app(DocumentTemplateHtmlSanitizer::class)->sanitize((string) $this->input('content_html'));
                app(DocumentTemplateHtmlSanitizer::class)->sanitize((string) $this->input('terms_html'));
                foreach (['content_html', 'terms_html'] as $field) {
                    $html = (string) $this->input($field);
                    if (str_contains($html, '{{') || str_contains($html, '}}')) {
                        $validator->errors()->add($field, 'Placeholder tidak boleh digunakan di editor item atau terms quotation.');
                    }
                }
            } catch (LogicException $exception) {
                $validator->errors()->add('content_html', $exception->getMessage());
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

        $senderId = $this->input('sender_id');
        if (! empty($senderId)) {
            $normalized['sender_id'] = (int) $senderId;
            $sender = User::query()->find($senderId);
            if ($sender && ! empty($sender->name)) {
                $normalized['sender_name'] = $sender->name;
            } elseif (empty($normalized['sender_name']) && $sender) {
                $normalized['sender_name'] = $sender->name ?: $sender->username;
            }
        } elseif ($this->user()) {
            $normalized['sender_id'] = $this->user()->getKey();
            if (! empty($this->user()->name)) {
                $normalized['sender_name'] = $this->user()->name;
            } elseif (empty($normalized['sender_name'])) {
                $normalized['sender_name'] = $this->user()->name ?: $this->user()->username;
            }
        }

        $normalized['currency'] = strtoupper(trim((string) $this->input('currency', 'IDR')));
        $this->merge($normalized);
    }

}
