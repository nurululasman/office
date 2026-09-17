<?php

namespace App\Services\DocumentTemplates;

use App\Models\Quotation;
use App\Services\Quotations\QuotationValueFormatter;
use Illuminate\Support\HtmlString;
use RuntimeException;

final class QuotationTemplateRenderer
{
    public function __construct(
        private readonly DocumentTemplateHtmlSanitizer $sanitizer,
        private readonly DocumentTemplatePlaceholderValidator $placeholders,
        private readonly QuotationValueFormatter $formatter,
    ) {}

    public function render(
        Quotation $quotation,
        bool $isDraft = true,
        ?string $logoSource = null,
        bool $requireActivationContract = true,
        ?string $stampSource = null,
        ?string $signatureSource = null,
        ?bool $showSenderSignatureAndStamp = null,
    ): string {
        $quotation->loadMissing(['document', 'terms', 'sender', 'creator']);
        $showSenderSignatureAndStamp ??= in_array($quotation->status, ['complete', 'void'], true)
            || $quotation->approved_at !== null;
        $snapshot = $quotation->template_snapshot;
        if (! is_array($snapshot) || ! is_string($snapshot['content_html'] ?? null)) {
            throw new RuntimeException('Quotation belum memiliki snapshot template yang valid.');
        }

        $templateHtml = $snapshot['content_html'];
        if (! hash_equals((string) $quotation->template_content_sha256, hash('sha256', $templateHtml))) {
            throw new RuntimeException('Checksum snapshot template quotation tidak cocok.');
        }
        $sanitizedTemplate = $this->sanitizer->sanitize($templateHtml);
        if (! hash_equals(hash('sha256', $templateHtml), hash('sha256', $sanitizedTemplate))) {
            throw new RuntimeException('Snapshot template tidak berada dalam bentuk HTML tersanitasi canonical.');
        }
        if ($requireActivationContract) {
            $this->placeholders->validateForActivation($sanitizedTemplate);
        } else {
            $this->placeholders->validateDraft($sanitizedTemplate);
        }

        $itemHtml = $quotation->content_html;
        if (! is_string($itemHtml) || $itemHtml === '') {
            throw new RuntimeException('Quotation belum memiliki konten item HTML.');
        }
        if (! hash_equals((string) $quotation->content_sha256, hash('sha256', $itemHtml))) {
            throw new RuntimeException('Checksum konten item HTML quotation tidak cocok.');
        }
        $sanitizedItems = $this->sanitizer->sanitize($itemHtml);
        if (! hash_equals(hash('sha256', $itemHtml), hash('sha256', $sanitizedItems))) {
            throw new RuntimeException('Konten item quotation tidak berada dalam bentuk HTML tersanitasi canonical.');
        }

        $termsHtml = $quotation->terms_html;
        if (! is_string($termsHtml) || $termsHtml === '') {
            throw new RuntimeException('Quotation belum memiliki konten terms HTML.');
        }
        if (! hash_equals((string) $quotation->terms_sha256, hash('sha256', $termsHtml))) {
            throw new RuntimeException('Checksum konten terms HTML quotation tidak cocok.');
        }
        $sanitizedTerms = $this->sanitizer->sanitize($termsHtml);
        if (! hash_equals(hash('sha256', $termsHtml), hash('sha256', $sanitizedTerms))) {
            throw new RuntimeException('Konten terms quotation tidak berada dalam bentuk HTML tersanitasi canonical.');
        }

        $scalar = $this->scalarValues($quotation, $snapshot, $isDraft);
        $structural = $this->structuralValues(
            $quotation,
            $snapshot,
            $isDraft,
            $logoSource,
            $sanitizedItems,
            $sanitizedTerms,
            $stampSource,
            $signatureSource,
            $showSenderSignatureAndStamp,
        );

        $rendered = $sanitizedTemplate;
        for ($pass = 0; $pass < 5; $pass++) {
            if (preg_match('/\{\{\s*[a-z][a-z0-9_]*\s*\}\}/', $rendered) !== 1) {
                break;
            }

            $rendered = preg_replace_callback(
                '/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/',
                function (array $match) use ($scalar, $structural): string {
                    $placeholder = $match[1];
                    if (array_key_exists($placeholder, $structural)) {
                        return $structural[$placeholder];
                    }
                    if (array_key_exists($placeholder, $scalar)) {
                        return $this->escapedMultiline($scalar[$placeholder]);
                    }

                    throw new RuntimeException("Placeholder {$placeholder} tidak dapat dirender.");
                },
                $rendered,
            );

            if (! is_string($rendered)) {
                throw new RuntimeException('Renderer gagal memproses placeholder quotation.');
            }
        }

        if (! is_string($rendered) || str_contains($rendered, '{{') || str_contains($rendered, '}}')) {
            throw new RuntimeException('Renderer meninggalkan placeholder yang belum diproses.');
        }

        return $rendered;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, string>
     */
    private function scalarValues(Quotation $quotation, array $snapshot, bool $isDraft): array
    {
        $company = is_array($snapshot['company_profile'] ?? null) ? $snapshot['company_profile'] : [];
        $address = array_values(array_filter([
            ...array_map('strval', is_array($company['address_lines'] ?? null) ? $company['address_lines'] : []),
        ], fn (string $line): bool => trim($line) !== ''));

        return [
            'quotation_number' => $quotation->document?->number
                ?? ($isDraft ? 'DRAFT — nomor belum terbit' : ''),
            'quotation_date' => $this->formatter->date($quotation->quotation_date->toDateString()),
            'subject' => (string) $quotation->subject,
            'customer_name' => (string) $quotation->customer_name,
            'customer_address' => (string) $quotation->customer_address,
            'attention_name' => (string) ($quotation->attention_name ?? ''),
            'attention_role' => (string) ($quotation->attention_role ?? ''),
            'sender_name' => (string) ($quotation->sender?->name ?: ($quotation->creator?->name ?: $quotation->sender_name)),
            'sender_title' => (string) $quotation->sender_title,
            'currency' => (string) $quotation->currency,
            'intro_text' => (string) ($quotation->intro_text ?? ''),
            'closing_text' => (string) ($quotation->closing_text ?? ''),
            'company_legal_name' => (string) ($company['legal_name'] ?? ''),
            'company_display_name' => (string) ($company['display_name'] ?? ''),
            'company_address' => implode("\n", $address),
            'company_email' => (string) ($company['email'] ?? ''),
            'company_phone' => (string) ($company['phone'] ?? ''),
            'company_website' => (string) ($company['website'] ?? ''),
            'company_bank_information' => (string) ($company['bank_information'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, string>
     */
    private function structuralValues(
        Quotation $quotation,
        array $snapshot,
        bool $isDraft,
        ?string $logoSource,
        string $itemHtml,
        string $termsHtml,
        ?string $stampSource = null,
        ?string $signatureSource = null,
        bool $showSenderSignatureAndStamp = true,
    ): array {
        $company = is_array($snapshot['company_profile'] ?? null) ? $snapshot['company_profile'] : [];

        return [
            'quotation_items' => $itemHtml,
            'company_logo' => view('quotation-templates.components.company-logo', [
                'logoSource' => $logoSource,
                'companyName' => (string) ($company['display_name'] ?? $company['legal_name'] ?? ''),
            ])->render(),
            'quotation_terms' => $termsHtml,
            'signature_block' => view('quotation-templates.components.signature', [
                'quotation' => $quotation,
                'stampSource' => $stampSource,
                'signatureSource' => $signatureSource,
                'showSenderSignatureAndStamp' => $showSenderSignatureAndStamp,
            ])->render(),
            'draft_watermark' => view('quotation-templates.components.draft-watermark', [
                'isDraft' => $isDraft,
            ])->render(),
        ];
    }

    private function escapedMultiline(string $value): string
    {
        return (new HtmlString(nl2br(e($value), false)))->toHtml();
    }
}
