<?php

namespace App\Services\DocumentTemplates;

use App\Models\Quotation;
use App\Services\Quotations\QuotationTableLayout;
use App\Services\Quotations\QuotationValueFormatter;
use Illuminate\Support\HtmlString;
use RuntimeException;

final class QuotationTemplateRenderer
{
    public function __construct(
        private readonly DocumentTemplateHtmlSanitizer $sanitizer,
        private readonly DocumentTemplatePlaceholderValidator $placeholders,
        private readonly QuotationValueFormatter $formatter,
        private readonly QuotationTableLayout $tableLayout,
        private readonly QuotationItemPresentation $itemPresentation,
    ) {}

    public function render(
        Quotation $quotation,
        bool $isDraft = true,
        ?string $logoSource = null,
        bool $requireActivationContract = true,
    ): string {
        $quotation->loadMissing(['document', 'items.values', 'terms']);
        $snapshot = $quotation->template_snapshot;
        if (! is_array($snapshot) || ! is_string($snapshot['content_html'] ?? null)) {
            throw new RuntimeException('Quotation belum memiliki snapshot template yang valid.');
        }

        $html = $snapshot['content_html'];
        if (! hash_equals((string) $quotation->template_content_sha256, hash('sha256', $html))) {
            throw new RuntimeException('Checksum snapshot template quotation tidak cocok.');
        }

        $sanitized = $this->sanitizer->sanitize($html);
        if (! hash_equals(hash('sha256', $html), hash('sha256', $sanitized))) {
            throw new RuntimeException('Snapshot template tidak berada dalam bentuk HTML tersanitasi canonical.');
        }
        if ($requireActivationContract) {
            $this->placeholders->validateForActivation($sanitized);
        } else {
            $this->placeholders->validateDraft($sanitized);
        }

        $scalar = $this->scalarValues($quotation, $snapshot, $isDraft);
        $structural = $this->structuralValues($quotation, $snapshot, $isDraft, $logoSource);

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
            $sanitized,
        );

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
            'sender_name' => (string) $quotation->sender_name,
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
    ): array {
        $schema = is_array($snapshot['item_schema'] ?? null)
            ? $snapshot['item_schema']
            : $quotation->item_schema;
        $company = is_array($snapshot['company_profile'] ?? null) ? $snapshot['company_profile'] : [];
        $presentation = $this->itemPresentation->resolve($schema);

        return [
            'company_logo' => view('quotation-templates.components.company-logo', [
                'logoSource' => $logoSource,
                'companyName' => (string) ($company['display_name'] ?? $company['legal_name'] ?? ''),
            ])->render(),
            'quotation_items' => $presentation['type'] === 'table'
                ? view('quotation-templates.components.items', [
                    'quotation' => $quotation,
                    'formatter' => $this->formatter,
                    'tableLayout' => $this->tableLayout->build($schema),
                ])->render()
                : view('quotation-templates.components.list', [
                    'nodes' => $this->listNodes($quotation, $schema, $presentation),
                    'tag' => $presentation['style'] === 'ordered' ? 'ol' : 'ul',
                ])->render(),
            'quotation_terms' => view('quotation-templates.components.terms', [
                'terms' => $quotation->terms,
            ])->render(),
            'signature_block' => view('quotation-templates.components.signature', [
                'quotation' => $quotation,
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

    /**
     * @param  array<string, mixed>  $schema
     * @param  array{type: string, style: string, content_key: string|null, max_depth: int}  $presentation
     * @return list<array{content: string, children: array}>
     */
    private function listNodes(Quotation $quotation, array $schema, array $presentation): array
    {
        $items = $quotation->items;
        $byId = $items->keyBy(fn ($item): string => (string) $item->getKey());
        if ($presentation['type'] === 'list' && $items->contains(fn ($item): bool => $item->parent_item_id !== null)) {
            throw new RuntimeException('Mode list tidak menerima sub-list.');
        }

        $children = [];
        foreach ($items as $item) {
            $parentId = $item->parent_item_id ? (string) $item->parent_item_id : '';
            if ($parentId !== '' && ! $byId->has($parentId)) {
                throw new RuntimeException('Parent item quotation tidak berada dalam snapshot quotation yang sama.');
            }
            $children[$parentId][] = $item;
        }

        $column = collect($schema['columns'] ?? [])->first(
            fn ($candidate): bool => is_array($candidate) && ($candidate['key'] ?? null) === $presentation['content_key'],
        );
        if (! is_array($column)) {
            throw new RuntimeException('Content key list tidak ditemukan pada item schema snapshot.');
        }

        $state = [];
        $build = function ($item, int $depth) use (&$build, &$state, $children, $column, $quotation, $presentation): array {
            $id = (string) $item->getKey();
            if (($state[$id] ?? null) === 'visiting') {
                throw new RuntimeException('Hierarchy item quotation mengandung circular reference.');
            }
            if ($depth > $presentation['max_depth']) {
                throw new RuntimeException('Hierarchy item quotation melebihi max depth template.');
            }

            $state[$id] = 'visiting';
            $value = $item->values->firstWhere('key', $presentation['content_key']);
            $node = [
                'content' => $this->formatter->format(
                    $value?->value,
                    (string) ($column['value_type'] ?? 'text'),
                    $quotation->currency,
                ),
                'children' => [],
            ];
            foreach ($children[$id] ?? [] as $child) {
                $node['children'][] = $build($child, $depth + 1);
            }
            $state[$id] = 'visited';

            return $node;
        };

        $nodes = [];
        foreach ($children[''] ?? [] as $root) {
            $nodes[] = $build($root, 1);
        }
        foreach ($items as $item) {
            if (($state[(string) $item->getKey()] ?? null) !== 'visited') {
                $build($item, 1);
                throw new RuntimeException('Hierarchy item quotation tidak memiliki root yang valid.');
            }
        }

        return $nodes;
    }
}
