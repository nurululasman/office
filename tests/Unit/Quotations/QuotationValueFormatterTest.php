<?php

namespace Tests\Unit\Quotations;

use App\Services\Quotations\QuotationValueFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuotationValueFormatterTest extends TestCase
{
    #[DataProvider('values')]
    public function test_it_formats_values_without_float_conversion(?string $value, string $type, string $expected): void
    {
        $this->assertSame($expected, (new QuotationValueFormatter)->format($value, $type, 'IDR'));
    }

    /** @return array<string, array{?string, string, string}> */
    public static function values(): array
    {
        return [
            'idr integer' => ['125000', 'currency', 'Rp 125.000'],
            'idr decimal rounds half up' => ['125000.50', 'currency', 'Rp 125.001'],
            'large exact value' => ['9007199254740993.25', 'currency', 'Rp 9.007.199.254.740.993'],
            'negative decimal' => ['-1234.50', 'decimal', '-1.234,5'],
            'integer grouping' => ['1000000', 'integer', '1.000.000'],
            'date' => ['2026-07-18', 'date', '18 Juli 2026'],
            'boolean true' => ['true', 'boolean', 'Ya'],
            'boolean false' => ['0', 'boolean', 'Tidak'],
            'empty' => [null, 'text', '—'],
        ];
    }
}
