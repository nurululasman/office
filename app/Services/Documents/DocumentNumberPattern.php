<?php

namespace App\Services\Documents;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class DocumentNumberPattern
{
    public const DATE_TOKENS = ['YYYY', 'YY', 'MM', 'MONTH_ROMAN'];

    /**
     * @param  array<int, mixed>  $segments
     * @return array<int, array{type: string, value?: string, width?: int}>
     */
    public function validateSegments(array $segments): array
    {
        if ($segments === [] || count($segments) > 30) {
            throw ValidationException::withMessages(['segments' => 'Pola wajib memiliki 1 sampai 30 segmen.']);
        }

        $normalized = [];
        $sequenceCount = 0;

        foreach ($segments as $index => $segment) {
            if (! is_array($segment) || ! isset($segment['type'])) {
                throw ValidationException::withMessages(["segments.$index" => 'Struktur segmen tidak valid.']);
            }

            if ($segment['type'] === 'literal') {
                $value = trim((string) ($segment['value'] ?? ''));
                if ($value === '' || mb_strlen($value) > 80) {
                    throw ValidationException::withMessages(["segments.$index.value" => 'Literal wajib diisi dan maksimal 80 karakter.']);
                }
                if (str_contains($value, '..') || preg_match('/[^\pL\pN ._\/-]/u', $value) === 1) {
                    throw ValidationException::withMessages(["segments.$index.value" => 'Literal hanya boleh memuat huruf, angka, spasi, titik, garis bawah, slash, dan hyphen tanpa path traversal.']);
                }
                $normalized[] = ['type' => 'literal', 'value' => $value];

                continue;
            }

            if ($segment['type'] === 'token') {
                $value = strtoupper((string) ($segment['value'] ?? ''));
                if (! in_array($value, self::DATE_TOKENS, true)) {
                    throw ValidationException::withMessages(["segments.$index.value" => 'Token tanggal tidak didukung.']);
                }
                $normalized[] = ['type' => 'token', 'value' => $value];

                continue;
            }

            if ($segment['type'] === 'sequence') {
                $width = filter_var($segment['width'] ?? null, FILTER_VALIDATE_INT);
                if ($width === false || $width < 1 || $width > 10) {
                    throw ValidationException::withMessages(["segments.$index.width" => 'Lebar sequence harus antara 1 dan 10.']);
                }
                $sequenceCount++;
                $normalized[] = ['type' => 'sequence', 'width' => $width];

                continue;
            }

            throw ValidationException::withMessages(["segments.$index.type" => 'Jenis segmen tidak didukung.']);
        }

        if ($sequenceCount !== 1) {
            throw ValidationException::withMessages(['segments' => 'Pola wajib memiliki tepat satu segmen sequence.']);
        }

        if (mb_strlen($this->toPattern($normalized)) > 255) {
            throw ValidationException::withMessages(['segments' => 'Pola menghasilkan nomor yang melebihi 255 karakter.']);
        }

        return $normalized;
    }

    /** @param array<int, array{type: string, value?: string, width?: int}> $segments */
    public function toPattern(array $segments): string
    {
        return implode('', array_map(fn (array $segment): string => match ($segment['type']) {
            'literal' => $segment['value'],
            'token' => '{'.$segment['value'].'}',
            'sequence' => '{SEQ:'.$segment['width'].'}',
        }, $segments));
    }

    /** @return array<int, array{type: string, value?: string, width?: int}> */
    public function fromPattern(string $pattern): array
    {
        preg_match_all('/\{(YYYY|YY|MM|MONTH_ROMAN|SEQ:(\d+))\}/', $pattern, $matches, PREG_OFFSET_CAPTURE);
        $segments = [];
        $offset = 0;

        foreach ($matches[0] as $index => [$match, $position]) {
            if ($position > $offset) {
                $segments[] = ['type' => 'literal', 'value' => substr($pattern, $offset, $position - $offset)];
            }
            $segments[] = str_starts_with($matches[1][$index][0], 'SEQ:')
                ? ['type' => 'sequence', 'width' => (int) $matches[2][$index][0]]
                : ['type' => 'token', 'value' => $matches[1][$index][0]];
            $offset = $position + strlen($match);
        }

        if ($offset < strlen($pattern)) {
            $segments[] = ['type' => 'literal', 'value' => substr($pattern, $offset)];
        }

        return $segments;
    }

    /** @param array<int, array{type: string, value?: string, width?: int}> $segments */
    public function preview(array $segments, CarbonInterface $issuedAt, int $sequence = 1): string
    {
        $monthRomans = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

        return implode('', array_map(fn (array $segment): string => match ($segment['type']) {
            'literal' => $segment['value'],
            'sequence' => str_pad((string) $sequence, $segment['width'], '0', STR_PAD_LEFT),
            'token' => match ($segment['value']) {
                'YYYY' => $issuedAt->format('Y'),
                'YY' => $issuedAt->format('y'),
                'MM' => $issuedAt->format('m'),
                'MONTH_ROMAN' => $monthRomans[(int) $issuedAt->format('n')],
            },
        }, $segments));
    }
}
