<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InitialDataCutoverTest extends TestCase
{
    use RefreshDatabase;

    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifestPath = storage_path('framework/testing/initial-data.json');
        @unlink($this->manifestPath);
    }

    protected function tearDown(): void
    {
        @unlink($this->manifestPath);
        parent::tearDown();
    }

    public function test_dual_approved_manifest_dry_runs_then_applies_idempotently(): void
    {
        $this->writeManifest();

        $this->assertSame(0, Artisan::call('office:initial-data:apply', ['manifest' => $this->manifestPath]));
        $this->assertDatabaseCount('document_types', 0);

        $arguments = ['manifest' => $this->manifestPath, '--apply' => true];
        $this->assertSame(0, Artisan::call('office:initial-data:apply', $arguments));
        $this->assertSame(0, Artisan::call('office:initial-data:apply', $arguments));

        $this->assertDatabaseCount('document_types', 1);
        $this->assertSame('QT-JBLU-{YYYY}{MM}{SEQ:4}', DocumentType::query()->sole()->number_pattern);
        $this->assertSame(118, DocumentSequence::query()->sole()->last_value);
        $this->assertSame('PT Jaya Baru Logistik', CompanyProfile::query()->sole()->legal_name);
        $this->assertSame('JBLU Quotation', DocumentTemplate::query()->sole()->name);
        $this->assertDatabaseHas('roles', ['slug' => 'system-admin']);
    }

    public function test_manifest_rejects_same_approver(): void
    {
        $manifest = $this->manifest();
        $manifest['approvals']['administrator'] = $manifest['approvals']['process_owner'];
        $this->write($manifest);

        $this->assertSame(1, Artisan::call('office:initial-data:apply', [
            'manifest' => $this->manifestPath,
            '--apply' => true,
        ]));
        $this->assertDatabaseCount('document_types', 0);
    }

    public function test_manifest_rejects_placeholders(): void
    {
        $manifest = $this->manifest();
        $manifest['source_reference'] = 'replace-with-export';
        $this->write($manifest);

        $this->assertSame(1, Artisan::call('office:initial-data:apply', [
            'manifest' => $this->manifestPath,
            '--apply' => true,
        ]));
        $this->assertDatabaseCount('document_types', 0);
    }

    public function test_cutover_sequence_can_never_be_lowered(): void
    {
        $this->writeManifest();
        $arguments = ['manifest' => $this->manifestPath, '--apply' => true];
        $this->assertSame(0, Artisan::call('office:initial-data:apply', $arguments));

        $manifest = $this->manifest();
        $manifest['sequences'][0]['last_value'] = 117;
        $this->write($manifest);

        $this->assertSame(1, Artisan::call('office:initial-data:apply', $arguments));
        $this->assertSame(118, DocumentSequence::query()->sole()->last_value);
    }

    private function writeManifest(): void
    {
        $this->write($this->manifest());
    }

    private function write(array $manifest): void
    {
        file_put_contents($this->manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    private function manifest(): array
    {
        return [
            'manifest_version' => 1,
            'source_reference' => 'legacy-register-2026-sha256:abc123',
            'approvals' => ['process_owner' => 'owner@example.test', 'administrator' => 'admin@example.test'],
            'company_profile' => [
                'company_code' => 'JBLU', 'legal_name' => 'PT Jaya Baru Logistik', 'display_name' => 'JBLU',
                'address_lines' => ['Jakarta'], 'city' => 'Jakarta', 'postal_code' => '10110', 'country' => 'ID',
            ],
            'document_types' => [[
                'code' => 'QUOTATION', 'name' => 'Quotation',
                'number_pattern' => 'QT-JBLU-{YYYY}{MM}{SEQ:4}', 'approval_mode' => 'direct',
            ]],
            'templates' => [[
                'type' => 'quotation', 'version' => 1, 'name' => 'JBLU Quotation',
                'settings' => ['columns' => [
                    ['key' => 'description', 'label' => 'Description', 'value_type' => 'text', 'required' => true],
                    ['key' => 'unit_price', 'label' => 'Unit Price', 'value_type' => 'currency', 'required' => true],
                ]],
            ]],
            'sequences' => [['document_type_code' => 'QUOTATION', 'period_year' => 2026, 'last_value' => 118]],
        ];
    }
}
