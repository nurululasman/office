<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\DocumentTemplate;
use App\Models\Quotation;
use App\Models\User;
use App\Services\DocumentTemplates\LegacyQuotationTemplateMigrator;
use App\Services\DocumentTemplates\QuotationTemplateRenderer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyQuotationTemplateMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_dry_run_reports_candidate_without_changing_data(): void
    {
        [$template] = $this->legacyAggregate();

        $this->artisan('office:quotation-templates:migrate-legacy')
            ->expectsOutputToContain('1 candidate')
            ->expectsOutputToContain('No data changed')
            ->assertSuccessful();

        $this->assertDatabaseCount('document_templates', 1);
        $this->assertSame('active', $template->refresh()->status);
    }

    public function test_apply_creates_active_version_preserves_schema_and_keeps_old_quotation_renderable(): void
    {
        [$legacy, $quotation] = $this->legacyAggregate();
        $actor = $this->administrator();
        $snapshotBefore = $quotation->template_snapshot;
        $htmlBefore = app(QuotationTemplateRenderer::class)->render($quotation);

        $this->artisan('office:quotation-templates:migrate-legacy', [
            '--apply' => true,
            '--actor' => $actor->email,
        ])->expectsOutputToContain('1 template version')->assertSuccessful();

        $replacement = DocumentTemplate::query()->where('status', 'active')->sole();
        $this->assertSame(2, $replacement->version);
        $this->assertSame(LegacyQuotationTemplateMigrator::MARKER, data_get($replacement->editor_config, 'migration.marker'));
        $this->assertSame($legacy->item_schema, $replacement->item_schema);
        $this->assertStringContainsString('{{ company_logo }}', $replacement->content_html);
        $this->assertSame('archived', $legacy->refresh()->status);
        $this->assertSame($snapshotBefore, $quotation->refresh()->template_snapshot);
        $this->assertSame($htmlBefore, app(QuotationTemplateRenderer::class)->render($quotation));

        $this->artisan('office:quotation-templates:migrate-legacy', [
            '--apply' => true,
            '--actor' => $actor->getKey(),
        ])->expectsOutputToContain('0 template version')->assertSuccessful();
        $this->assertDatabaseCount('document_templates', 2);
    }

    public function test_rollback_reactivates_legacy_version_without_deleting_migrated_version_or_snapshot(): void
    {
        [$legacy, $quotation] = $this->legacyAggregate();
        $actor = $this->administrator();
        $snapshotBefore = $quotation->template_snapshot;
        app(LegacyQuotationTemplateMigrator::class)->migrate($actor);
        $replacement = DocumentTemplate::query()->where('status', 'active')->sole();

        $this->artisan('office:quotation-templates:migrate-legacy', [
            '--rollback' => true,
            '--actor' => $actor->email,
        ])->expectsOutputToContain('1 template family')->assertSuccessful();

        $this->assertSame('active', $legacy->refresh()->status);
        $this->assertSame('archived', $replacement->refresh()->status);
        $this->assertDatabaseCount('document_templates', 2);
        $this->assertSame($snapshotBefore, $quotation->refresh()->template_snapshot);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'quotation_template.migration_rolled_back',
            'subject_id' => $legacy->getKey(),
            'actor_id' => $actor->getKey(),
        ]);
    }

    /** @return array{DocumentTemplate, Quotation} */
    private function legacyAggregate(): array
    {
        $creator = User::factory()->create();
        $profile = CompanyProfile::query()->create([
            'company_code' => 'JBLU', 'legal_name' => 'PT JBLU', 'display_name' => 'JBLU',
            'address_lines' => ['Jakarta'], 'city' => 'Jakarta', 'postal_code' => '10110', 'country' => 'ID',
        ]);
        $schema = ['columns' => [
            ['key' => 'description', 'label' => 'Description', 'value_type' => 'text', 'required' => true],
            ['key' => 'rate', 'label' => 'Rate', 'value_type' => 'currency', 'required' => true],
        ]];
        $template = $profile->templates()->create([
            'type' => 'quotation', 'template_key' => 'quotation-default', 'version' => 1,
            'name' => 'Legacy quotation', 'status' => 'active',
            'content_html' => DocumentTemplate::LEGACY_CONTENT_HTML,
            'settings' => $schema, 'item_schema' => $schema,
        ]);
        $quotation = Quotation::query()->create([
            'created_by' => $creator->getKey(), 'template_id' => $template->getKey(),
            'status' => 'draft', 'approval_mode' => 'direct', 'quotation_date' => '2026-07-23',
            'subject' => 'Legacy snapshot', 'customer_name' => 'Customer',
            'customer_address' => 'Jakarta', 'sender_name' => 'Sales',
            'sender_title' => 'Manager', 'currency' => 'IDR', 'item_schema' => $schema,
        ]);
        $item = $quotation->items()->create(['position' => 1]);
        $item->values()->createMany([
            ['key' => 'description', 'value' => 'Storage', 'value_type' => 'text', 'position' => 1],
            ['key' => 'rate', 'value' => '100000', 'value_type' => 'currency', 'position' => 2],
        ]);

        return [$template, $quotation];
    }

    private function administrator(): User
    {
        $actor = User::factory()->create(['is_active' => true]);
        $actor->assignRole('system-admin');

        return $actor;
    }
}
