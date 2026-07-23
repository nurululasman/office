<?php

namespace App\Services\DocumentTemplates;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use LogicException;

final class DocumentTemplateHtmlSanitizer
{
    public const MAX_HTML_BYTES = 200_000;

    /** @var array<string, list<string>> */
    private const ALLOWED_ATTRIBUTES = [
        'p' => ['class', 'style'],
        'h1' => ['class', 'style'],
        'h2' => ['class', 'style'],
        'h3' => ['class', 'style'],
        'h4' => ['class', 'style'],
        'blockquote' => ['class', 'style'],
        'ul' => ['class', 'style'],
        'ol' => ['class', 'style', 'start'],
        'li' => ['class', 'style'],
        'table' => ['class', 'style', 'border'],
        'thead' => ['class', 'style'],
        'tbody' => ['class', 'style'],
        'tfoot' => ['class', 'style'],
        'tr' => ['class', 'style'],
        'th' => ['class', 'style', 'colspan', 'rowspan', 'scope'],
        'td' => ['class', 'style', 'colspan', 'rowspan'],
        'div' => ['class', 'style'],
        'span' => ['class', 'style'],
        'hr' => ['class'],
    ];

    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'h1', 'h2', 'h3', 'h4', 'blockquote', 'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'div', 'span', 'hr',
    ];

    /** @var list<string> */
    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'textarea', 'select', 'option', 'video', 'audio', 'svg',
        'math', 'template', 'link', 'meta', 'base',
    ];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            throw new LogicException('Konten template tidak boleh kosong.');
        }
        if (strlen($html) > self::MAX_HTML_BYTES) {
            throw new LogicException('Konten template tidak boleh melebihi 200.000 byte.');
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML(
            '<!doctype html><html><head><meta charset="UTF-8"></head><body><div id="template-root">'.$html.'</div></body></html>',
            LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new LogicException('Konten template bukan HTML yang dapat diproses.');
        }

        $root = (new DOMXPath($document))->query('//*[@id="template-root"]')->item(0);
        if (! $root instanceof DOMElement) {
            throw new LogicException('Konten template tidak dapat dinormalisasi.');
        }

        $this->sanitizeChildren($root);

        $sanitized = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $sanitized .= $document->saveHTML($child);
        }

        $sanitized = trim($sanitized);
        if ($sanitized === '') {
            throw new LogicException('Konten template kosong setelah sanitasi.');
        }
        if (strlen($sanitized) > self::MAX_HTML_BYTES) {
            throw new LogicException('Konten template hasil sanitasi tidak boleh melebihi 200.000 byte.');
        }

        return $sanitized;
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE) {
                $parent->removeChild($node);

                continue;
            }
            if (! $node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->sanitizeChildren($node);
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            $this->sanitizeAttributes($node, $tag);
            $this->sanitizeChildren($node);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if (! in_array($name, $allowed, true)) {
                $element->removeAttributeNode($attribute);

                continue;
            }

            $value = trim($attribute->value);
            $normalized = match ($name) {
                'class' => $this->sanitizeClass($value),
                'style' => $this->sanitizeStyle($value, $tag),
                'start', 'border', 'colspan', 'rowspan' => preg_match('/\A\d{1,3}\z/', $value) ? $value : '',
                'scope' => in_array($value, ['row', 'col', 'rowgroup', 'colgroup'], true) ? $value : '',
                default => '',
            };

            if ($normalized === '') {
                $element->removeAttribute($name);
            } else {
                $element->setAttribute($name, $normalized);
            }
        }
    }

    private function sanitizeClass(string $value): string
    {
        $classes = array_filter(
            preg_split('/\s+/', $value) ?: [],
            fn (string $class): bool => in_array($class, ['page-break'], true),
        );

        return implode(' ', array_unique($classes));
    }

    private function sanitizeStyle(string $value, string $tag): string
    {
        $allowed = [];
        foreach (explode(';', $value) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }
            [$property, $propertyValue] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $propertyValue = strtolower($propertyValue);

            $valid = match ($property) {
                'text-align' => in_array($propertyValue, ['left', 'center', 'right', 'justify'], true),
                'vertical-align' => in_array($tag, ['th', 'td'], true)
                    && in_array($propertyValue, ['top', 'middle', 'bottom'], true),
                'margin-left' => in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4'], true)
                    && preg_match('/\A(?:0|[1-9]\d?(?:\.\d+)?)(?:px|mm)\z/', $propertyValue) === 1,
                'border-collapse' => $tag === 'table' && $propertyValue === 'collapse',
                'width' => $tag === 'table'
                    && preg_match('/\A(?:100|[1-9]?\d)(?:\.\d+)?%\z/', $propertyValue) === 1,
                default => false,
            };

            if ($valid) {
                $allowed[$property] = $propertyValue;
            }
        }

        return implode('; ', array_map(
            fn (string $property, string $propertyValue): string => "{$property}: {$propertyValue}",
            array_keys($allowed),
            array_values($allowed),
        ));
    }
}
