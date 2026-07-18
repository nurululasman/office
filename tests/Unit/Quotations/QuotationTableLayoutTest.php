<?php

namespace Tests\Unit\Quotations;

use App\Services\Quotations\QuotationTableLayout;
use PHPUnit\Framework\TestCase;

class QuotationTableLayoutTest extends TestCase
{
    public function test_it_builds_optional_contiguous_grouped_headers_for_general_keys(): void
    {
        $layout = (new QuotationTableLayout)->build(['columns' => [
            ['key' => 'service', 'label' => 'Service', 'value_type' => 'text', 'width' => 30],
            ['key' => 'small', 'label' => "20'", 'group' => 'Container', 'value_type' => 'currency'],
            ['key' => 'large', 'label' => "40'", 'group' => 'Container', 'value_type' => 'currency'],
            ['key' => 'active', 'label' => 'Active', 'value_type' => 'boolean', 'align' => 'center'],
        ]]);

        $this->assertTrue($layout['has_grouped_header']);
        $this->assertCount(3, $layout['header_blocks']);
        $this->assertFalse($layout['header_blocks'][0]['grouped']);
        $this->assertSame('Container', $layout['header_blocks'][1]['label']);
        $this->assertCount(2, $layout['header_blocks'][1]['columns']);
        $this->assertSame(['service', 'small', 'large', 'active'], array_column($layout['columns'], 'key'));
        $this->assertSame('right', $layout['columns'][1]['align']);
        $this->assertSame('center', $layout['columns'][3]['align']);
    }

    public function test_it_keeps_single_header_row_when_groups_are_absent_and_sanitizes_layout_hints(): void
    {
        $layout = (new QuotationTableLayout)->build(['columns' => [
            ['key' => 'description', 'label' => 'Description', 'value_type' => 'unknown', 'width' => 99, 'align' => 'invalid'],
            ['invalid' => true],
        ]]);

        $this->assertFalse($layout['has_grouped_header']);
        $this->assertCount(1, $layout['columns']);
        $this->assertSame('text', $layout['columns'][0]['value_type']);
        $this->assertSame(80, $layout['columns'][0]['width']);
        $this->assertSame('left', $layout['columns'][0]['align']);
    }
}
