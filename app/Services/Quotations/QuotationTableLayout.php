<?php

namespace App\Services\Quotations;

final class QuotationTableLayout
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array{columns: list<array<string, mixed>>, header_blocks: list<array{label: string, grouped: bool, columns: list<array<string, mixed>>}>, has_grouped_header: bool}
     */
    public function build(array $schema): array
    {
        $columns = [];
        foreach ($schema['columns'] ?? [] as $position => $column) {
            if (! is_array($column) || ! is_string($column['key'] ?? null) || ! is_string($column['label'] ?? null)) {
                continue;
            }

            $type = in_array($column['value_type'] ?? null, ['text', 'decimal', 'integer', 'date', 'boolean', 'currency'], true)
                ? $column['value_type'] : 'text';
            $group = trim((string) ($column['group'] ?? $column['header_group'] ?? '')) ?: null;
            $width = is_numeric($column['width'] ?? null) ? min(80, max(5, (float) $column['width'])) : null;
            $align = in_array($column['align'] ?? null, ['left', 'center', 'right'], true)
                ? $column['align'] : (in_array($type, ['decimal', 'integer', 'currency'], true) ? 'right' : 'left');

            $columns[] = [
                'key' => $column['key'], 'label' => $column['label'], 'value_type' => $type,
                'group' => $group, 'width' => $width, 'align' => $align, 'position' => $position,
            ];
        }

        $blocks = [];
        foreach ($columns as $column) {
            $last = array_key_last($blocks);
            if ($column['group'] !== null && $last !== null && $blocks[$last]['grouped'] && $blocks[$last]['label'] === $column['group']) {
                $blocks[$last]['columns'][] = $column;

                continue;
            }

            $blocks[] = [
                'label' => $column['group'] ?? $column['label'],
                'grouped' => $column['group'] !== null,
                'columns' => [$column],
            ];
        }

        return [
            'columns' => $columns,
            'header_blocks' => $blocks,
            'has_grouped_header' => collect($blocks)->contains('grouped', true),
        ];
    }
}
