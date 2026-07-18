<?php

namespace App\Services\Quotations;

use DateTimeImmutable;

final class QuotationValueFormatter
{
    public function format(?string $value, string $valueType, string $currency = 'IDR'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($valueType) {
            'currency' => $this->currency($value, $currency),
            'decimal' => $this->number($value, false),
            'integer' => $this->number($value, true),
            'date' => $this->date($value),
            'boolean' => in_array(strtolower($value), ['1', 'true'], true) ? 'Ya' : 'Tidak',
            default => $value,
        };
    }

    public function date(string|DateTimeImmutable $value): string
    {
        $date = $value instanceof DateTimeImmutable ? $value : DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date) {
            return (string) $value;
        }

        $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return $date->format('j').' '.$months[(int) $date->format('n')].' '.$date->format('Y');
    }

    private function currency(string $value, string $currency): string
    {
        if (strtoupper($currency) === 'IDR') {
            return 'Rp '.$this->number($this->roundToWhole($value), true);
        }

        $formatted = $this->number($value, false);

        return strtoupper($currency).' '.$formatted;
    }

    private function roundToWhole(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';

        if ($fraction !== '' && $fraction[0] >= '5') {
            $digits = str_split($whole);
            $carry = 1;
            for ($index = count($digits) - 1; $index >= 0 && $carry; $index--) {
                $next = ((int) $digits[$index]) + $carry;
                $digits[$index] = (string) ($next % 10);
                $carry = intdiv($next, 10);
            }
            $whole = ($carry ? '1' : '').implode('', $digits);
        }

        return ($negative ? '-' : '').$whole;
    }

    private function number(string $value, bool $integer): string
    {
        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, null);
        $whole = ltrim($whole, '0') ?: '0';
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);

        if (! $integer && $fraction !== null && trim($fraction, '0') !== '') {
            $grouped .= ','.rtrim($fraction, '0');
        }

        return ($negative ? '-' : '').$grouped;
    }
}
