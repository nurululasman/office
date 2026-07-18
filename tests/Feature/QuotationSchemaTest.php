<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Models\Quotation;
use App\Models\QuotationItemValue;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_aggregate_preserves_dynamic_values_terms_template_and_file_metadata(): void
    {
        $user = User::factory()->create();
        $profile = CompanyProfile::query()->create([
            'company_code' => 'JBLU', 'legal_name' => 'PT Jaya Berlian Logistics Utama',
            'display_name' => 'JBLU', 'address_lines' => ['Jl. Test'], 'city' => 'Jakarta',
            'postal_code' => '10110', 'country' => 'ID',
        ]);
        $template = $profile->templates()->create([
            'type' => 'quotation', 'version' => 1, 'name' => 'Quotation v1',
            'settings' => ['columns' => [['key' => 'service', 'value_type' => 'text']]],
        ]);
        $quotation = Quotation::query()->create([
            'created_by' => $user->getKey(),
            'template_id' => $template->getKey(),
            'status' => 'draft', 'approval_mode' => 'direct', 'quotation_date' => '2026-07-18',
            'subject' => 'Storage rates', 'customer_name' => 'Customer A',
            'customer_address' => 'Jakarta', 'sender_name' => $user->name,
            'sender_title' => 'Sales', 'item_schema' => $template->settings, 'currency' => 'IDR',
        ]);
        $item = $quotation->items()->create(['position' => 1]);
        $value = $item->values()->create([
            'key' => 'unit_price', 'value' => '125000.50', 'value_type' => 'currency', 'position' => 1,
        ]);
        $term = $quotation->terms()->create(['position' => 1, 'content' => 'VAT excluded']);
        $file = $quotation->generatedFiles()->create([
            'template_id' => $template->getKey(), 'kind' => 'preview', 'disk' => 'local',
            'path' => 'quotations/example.pdf', 'mime_type' => 'application/pdf', 'size' => 123,
            'sha256' => str_repeat('a', 64), 'generated_at' => now(), 'generated_by' => $user->getKey(),
        ]);

        $this->assertTrue(Str::isUuid($quotation->getKey()));
        $this->assertSame('125000.50', $value->value);
        $this->assertSame('unit_price', $quotation->items->first()->values->first()->key);
        $this->assertTrue($term->quotation->is($quotation));
        $this->assertTrue($file->owner->is($quotation));
        $this->assertTrue($file->template->is($template));
        $this->assertSame('service', $quotation->item_schema['columns'][0]['key']);
        $this->assertSame(1, $template->version);
    }

    public function test_template_versions_are_unique_per_document_type(): void
    {
        $template = $this->createTemplate();

        $this->expectException(QueryException::class);
        DocumentTemplate::query()->create([
            'company_profile_id' => $template->company_profile_id,
            'type' => $template->type, 'version' => $template->version,
            'name' => 'Duplicate version', 'settings' => [],
        ]);
    }

    public function test_item_keys_and_positions_are_unique_within_an_item(): void
    {
        $quotation = $this->createQuotation();
        $item = $quotation->items()->create(['position' => 1]);
        QuotationItemValue::query()->create([
            'quotation_item_id' => $item->getKey(), 'key' => 'rate_20',
            'value' => '100000', 'value_type' => 'currency', 'position' => 1,
        ]);

        $this->expectException(QueryException::class);
        QuotationItemValue::query()->create([
            'quotation_item_id' => $item->getKey(), 'key' => 'rate_20',
            'value' => '200000', 'value_type' => 'currency', 'position' => 2,
        ]);
    }

    public function test_deleting_a_quotation_cascades_items_values_and_terms(): void
    {
        $quotation = $this->createQuotation();
        $item = $quotation->items()->create(['position' => 1]);
        $value = $item->values()->create(['key' => 'note', 'value' => 'x', 'value_type' => 'text', 'position' => 1]);
        $term = $quotation->terms()->create(['position' => 1, 'content' => 'A term']);

        $quotation->delete();

        $this->assertDatabaseMissing('quotation_items', ['id' => $item->getKey()]);
        $this->assertDatabaseMissing('quotation_item_values', ['id' => $value->getKey()]);
        $this->assertDatabaseMissing('quotation_terms', ['id' => $term->getKey()]);
    }

    private function createQuotation(): Quotation
    {
        $user = User::factory()->create();
        $template = $this->createTemplate();

        return Quotation::query()->create([
            'created_by' => $user->getKey(),
            'template_id' => $template->getKey(),
            'approval_mode' => 'direct', 'quotation_date' => '2026-07-18', 'subject' => 'Test',
            'customer_name' => 'Customer', 'customer_address' => 'Jakarta',
            'sender_name' => 'Sender', 'sender_title' => 'Sales', 'item_schema' => [],
        ]);
    }

    private function createTemplate(): DocumentTemplate
    {
        $profile = CompanyProfile::query()->create([
            'company_code' => 'JBLU', 'legal_name' => 'PT JBLU', 'display_name' => 'JBLU',
            'address_lines' => [], 'city' => 'Jakarta', 'postal_code' => '10110', 'country' => 'ID',
        ]);

        return $profile->templates()->create([
            'type' => 'quotation', 'version' => 1, 'name' => 'Quotation v1', 'settings' => [],
        ]);
    }
}
