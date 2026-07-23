<?php

namespace App\Services\DocumentTemplates;

use LogicException;

final class QuotationItemPresentation
{
    /**
     * @param  array<string, mixed>  $schema
     * @return array{type: string, style: string, content_key: string|null, max_depth: int}
     */
    public function resolve(array $schema): array
    {
        $presentation = is_array($schema['presentation'] ?? null) ? $schema['presentation'] : [];
        $type = (string) ($presentation['type'] ?? 'table');
        if (! in_array($type, ['table', 'list', 'nested_list'], true)) {
            throw new LogicException('Tipe presentasi item harus table, list, atau nested_list.');
        }

        if ($type === 'table') {
            return ['type' => 'table', 'style' => 'unordered', 'content_key' => null, 'max_depth' => 1];
        }

        $style = (string) ($presentation['style'] ?? 'unordered');
        if (! in_array($style, ['ordered', 'unordered'], true)) {
            throw new LogicException('Style list item harus ordered atau unordered.');
        }

        $columns = collect($schema['columns'] ?? []);
        $contentKey = (string) ($presentation['content_key'] ?? '');
        if ($contentKey === '' || ! $columns->contains(fn ($column): bool => is_array($column) && ($column['key'] ?? null) === $contentKey)) {
            throw new LogicException('Content key list harus merujuk kolom item schema.');
        }

        $maxDepth = $type === 'list' ? 1 : (int) ($presentation['max_depth'] ?? 3);
        if ($type === 'nested_list' && ($maxDepth < 2 || $maxDepth > 5)) {
            throw new LogicException('Max depth nested list harus antara 2 dan 5.');
        }

        return [
            'type' => $type,
            'style' => $style,
            'content_key' => $contentKey,
            'max_depth' => $maxDepth,
        ];
    }
}
