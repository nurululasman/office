<?php

namespace App\Services\DocumentTemplates;

use App\Models\DocumentTemplate;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationItemValue;
use App\Models\QuotationTerm;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DocumentTemplatePreviewFactory
{
    public function __construct(
        private readonly QuotationItemPresentation $presentation,
    ) {}

    public function make(DocumentTemplate $template): Quotation
    {
        $template->loadMissing('companyProfile');
        $schema = is_array($template->item_schema) ? $template->item_schema : ['columns' => []];
        $quotation = new Quotation([
            'quotation_date' => now()->toDateString(),
            'subject' => 'Penawaran Layanan Logistik',
            'customer_name' => 'PT Pelanggan Contoh',
            'customer_address' => "Jl. Contoh No. 123\nJakarta",
            'attention_name' => 'Bapak/Ibu Pelanggan',
            'attention_role' => 'Manajer Operasional',
            'sender_name' => 'Nama Penandatangan',
            'sender_title' => 'Direktur',
            'currency' => 'IDR',
            'intro_text' => $template->default_intro_text ?: 'Dengan hormat, berikut kami sampaikan penawaran layanan.',
            'closing_text' => $template->default_closing_text ?: 'Demikian penawaran ini kami sampaikan. Terima kasih.',
            'item_schema' => $schema,
            'template_snapshot' => $template->snapshot(),
            'template_content_sha256' => $template->content_sha256,
            'status' => 'draft',
        ]);

        $quotation->setRelation('document', null);
        $quotation->setRelation('items', $this->items($schema));
        $quotation->setRelation('terms', $this->terms($template));

        return $quotation;
    }

    /** @param array<string, mixed> $schema */
    private function items(array $schema): Collection
    {
        $presentation = $this->presentation->resolve($schema);
        $count = $presentation['type'] === 'nested_list' ? 3 : 2;
        $items = collect();

        for ($index = 1; $index <= $count; $index++) {
            $item = new QuotationItem(['position' => $index]);
            $item->setAttribute($item->getKeyName(), (string) Str::uuid());
            if ($presentation['type'] === 'nested_list' && $index === 2) {
                $item->parent_item_id = $items->first()?->getKey();
            }
            $item->setRelation('values', $this->values($schema, $index));
            $items->push($item);
        }

        return $items;
    }

    /** @param array<string, mixed> $schema */
    private function values(array $schema, int $row): Collection
    {
        return collect($schema['columns'] ?? [])
            ->filter(fn ($column): bool => is_array($column) && is_string($column['key'] ?? null))
            ->values()
            ->map(function (array $column, int $position) use ($row): QuotationItemValue {
                $type = (string) ($column['value_type'] ?? 'text');

                return new QuotationItemValue([
                    'key' => $column['key'],
                    'value' => $this->sampleValue($type, (string) ($column['label'] ?? $column['key']), $row),
                    'value_type' => $type,
                    'position' => $position + 1,
                ]);
            });
    }

    private function sampleValue(string $type, string $label, int $row): string
    {
        return match ($type) {
            'number' => (string) ($row * 10),
            'currency' => (string) ($row * 1_500_000),
            'date' => now()->addDays($row)->toDateString(),
            'boolean' => $row % 2 === 1 ? '1' : '0',
            default => "{$label} contoh {$row}",
        };
    }

    private function terms(DocumentTemplate $template): Collection
    {
        $terms = $template->default_terms ?: [
            'Harga belum termasuk pajak yang berlaku.',
            'Masa berlaku penawaran adalah 30 hari.',
        ];

        return collect($terms)->values()->map(
            fn ($term, int $position) => new QuotationTerm([
                'position' => $position + 1,
                'content' => (string) $term,
            ]),
        );
    }
}
