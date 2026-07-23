<?php

namespace App\Services\DocumentTemplates;

use DOMDocument;
use DOMElement;
use DOMXPath;
use LogicException;

final class DocumentTemplatePlaceholderValidator
{
    /** @var list<string> */
    public const SCALAR = [
        'quotation_number', 'quotation_date', 'subject', 'customer_name',
        'customer_address', 'attention_name', 'attention_role', 'sender_name',
        'sender_title', 'currency', 'intro_text', 'closing_text',
        'company_legal_name', 'company_display_name', 'company_address',
        'company_email', 'company_phone', 'company_website',
    ];

    /** @var list<string> */
    public const STRUCTURAL = [
        'company_logo', 'quotation_items', 'quotation_terms',
        'signature_block', 'draft_watermark',
    ];

    /** @var list<string> */
    private const REQUIRED = [
        'quotation_number', 'quotation_date', 'subject', 'customer_name',
        'sender_name',
    ];

    public function validateDraft(string $html): void
    {
        $counts = $this->placeholderCounts($html);
        foreach (self::STRUCTURAL as $placeholder) {
            if (($counts[$placeholder] ?? 0) > 1) {
                throw new LogicException("Placeholder {{$placeholder}} hanya boleh digunakan satu kali.");
            }
        }

        $this->validateStructuralPlacement($html);
    }

    public function validateForActivation(string $html): void
    {
        $this->validateDraft($html);
        $counts = $this->placeholderCounts($html);

        foreach (self::REQUIRED as $placeholder) {
            if (($counts[$placeholder] ?? 0) < 1) {
                throw new LogicException("Placeholder {{$placeholder}} wajib ada sebelum template diaktifkan.");
            }
        }
        if (($counts['quotation_items'] ?? 0) !== 1) {
            throw new LogicException('Placeholder {quotation_items} wajib ada tepat satu kali sebelum template diaktifkan.');
        }
        if (($counts['company_display_name'] ?? 0) < 1 && ($counts['company_legal_name'] ?? 0) < 1) {
            throw new LogicException('Placeholder company_display_name atau company_legal_name wajib ada sebelum template diaktifkan.');
        }
    }

    /** @return array<string, int> */
    public function placeholderCounts(string $html): array
    {
        preg_match_all('/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/', $html, $matches);
        $tokens = $matches[1] ?? [];
        $recognizedFragments = preg_replace('/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/', '', $html);
        if (str_contains((string) $recognizedFragments, '{{') || str_contains((string) $recognizedFragments, '}}')) {
            throw new LogicException('Sintaks placeholder tidak valid.');
        }

        $unknown = array_values(array_diff(array_unique($tokens), [...self::SCALAR, ...self::STRUCTURAL]));
        if ($unknown !== []) {
            throw new LogicException('Placeholder tidak dikenal: '.implode(', ', $unknown).'.');
        }

        return array_count_values($tokens);
    }

    private function validateStructuralPlacement(string $html): void
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!doctype html><html><head><meta charset="UTF-8"></head><body><div id="placeholder-root">'.$html.'</div></body></html>',
            LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        foreach ($xpath->query('//*[@id="placeholder-root"]//text()[contains(., "{{")]') ?: [] as $textNode) {
            preg_match_all('/\{\{\s*([a-z][a-z0-9_]*)\s*\}\}/', $textNode->nodeValue, $matches);
            foreach ($matches[1] ?? [] as $placeholder) {
                if (! in_array($placeholder, self::STRUCTURAL, true)) {
                    continue;
                }

                $parent = $textNode->parentNode;
                if (! $parent instanceof DOMElement
                    || strtolower($parent->tagName) !== 'div'
                    || trim($parent->textContent) !== trim($textNode->nodeValue)
                    || $parent->getElementsByTagName('*')->length > 0) {
                    throw new LogicException("Placeholder struktural {{$placeholder}} harus berdiri sendiri di dalam elemen div.");
                }
            }
        }
    }
}
