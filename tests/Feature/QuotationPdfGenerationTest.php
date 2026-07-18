<?php

namespace Tests\Feature;

use App\Jobs\GenerateQuotationPdf;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\GeneratedFile;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotations\QuotationPdfRenderer;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class QuotationPdfGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_completion_dispatches_one_after_commit_job_to_pdf_queue(): void
    {
        Queue::fake();
        $maker = User::factory()->create();
        $maker->assignRole('quotation-maker');
        $quotation = $this->quotation($maker);

        $this->actingAs($maker)->withSession(['office.sso.tokens' => [
            'access_token' => 'encrypted', 'refresh_token' => null,
            'expires_at' => time() + 3600, 'authenticated_at' => time(),
        ]])->post(route('quotations.complete', $quotation), ['lock_version' => 0])->assertRedirect();

        $file = GeneratedFile::query()->sole();
        $this->assertSame('queued', $file->status);
        $this->assertSame('documents', $file->disk);
        $this->assertSame("quotations/{$quotation->getKey()}/quotation.pdf", $file->path);
        $this->assertNull($file->sha256);
        Queue::assertPushedOn('pdf', GenerateQuotationPdf::class);
        Queue::assertPushed(GenerateQuotationPdf::class, 1);

        $this->post(route('quotations.complete', $quotation), ['lock_version' => 0])->assertRedirect();
        $this->assertDatabaseCount('generated_files', 1);
        Queue::assertPushed(GenerateQuotationPdf::class, 1);
    }

    public function test_job_records_attempt_and_is_idempotent_after_ready(): void
    {
        $file = $this->completeFile();
        $renderer = Mockery::mock(QuotationPdfRenderer::class);
        $renderer->shouldReceive('render')->once()->andReturnUsing(function (GeneratedFile $generatedFile): void {
            $generatedFile->update([
                'status' => 'ready', 'size' => 4, 'sha256' => hash('sha256', '%PDF'),
                'generated_at' => now('UTC'),
            ]);
        });

        $job = new GenerateQuotationPdf($file->getKey());
        $job->handle($renderer);
        $this->assertSame('ready', $file->refresh()->status);
        $this->assertSame(1, $file->attempts);
        $job->handle($renderer);
        $this->assertSame(1, $file->refresh()->attempts);
    }

    public function test_job_records_failure_without_losing_retry_state(): void
    {
        $file = $this->completeFile();
        $renderer = Mockery::mock(QuotationPdfRenderer::class);
        $renderer->shouldReceive('render')->once()->andThrow(new RuntimeException('Chrome failed safely'));

        try {
            (new GenerateQuotationPdf($file->getKey()))->handle($renderer);
            $this->fail('Expected renderer exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Chrome failed safely', $exception->getMessage());
        }

        $file->refresh();
        $this->assertSame('failed', $file->status);
        $this->assertSame(1, $file->attempts);
        $this->assertSame('Chrome failed safely', $file->last_error);
        $this->assertNull($file->generated_at);
    }

    public function test_real_chrome_renderer_writes_private_pdf_and_metadata(): void
    {
        if (! filter_var(env('OFFICE_RUN_PDF_RENDERER_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set OFFICE_RUN_PDF_RENDERER_TESTS=true untuk menjalankan gate Chrome PDF.');
        }

        config(['filesystems.disks.pdf-proof' => [
            'driver' => 'local', 'root' => base_path('output/pdf'), 'serve' => false, 'throw' => true,
        ]]);
        $file = $this->completeFile();
        $file->update(['disk' => 'pdf-proof', 'path' => 'OFF-0403-renderer-proof.pdf']);

        app(QuotationPdfRenderer::class)->render($file->refresh());

        $contents = Storage::disk('pdf-proof')->get($file->path);
        $file->refresh();
        $this->assertStringStartsWith('%PDF-', $contents);
        $this->assertSame('ready', $file->status);
        $this->assertSame(strlen($contents), $file->size);
        $this->assertSame(hash('sha256', $contents), $file->sha256);
        $this->assertNotNull($file->generated_at);
    }

    public function test_authorized_user_can_preview_and_download_ready_private_pdf_with_stable_name(): void
    {
        Storage::fake('documents');
        $file = $this->completeFile();
        $contents = '%PDF-1.7 private quotation';
        Storage::disk('documents')->put($file->path, $contents);
        $file->update([
            'status' => 'ready', 'size' => strlen($contents), 'sha256' => hash('sha256', $contents),
            'generated_at' => now('UTC'),
        ]);
        $quotation = Quotation::query()->findOrFail($file->owner_id);
        $quotation->creator->assignRole('quotation-maker');

        $preview = $this->as($quotation->creator)->get(route('quotations.pdf.preview', $quotation));
        $preview->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('cache-control', 'max-age=0, no-store, private')
            ->assertHeader('content-disposition', 'inline; filename=Quotation-QT-JBLU-2026070001.pdf');
        $this->assertSame($contents, $preview->streamedContent());

        $download = $this->get(route('quotations.pdf.download', $quotation));
        $download->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=Quotation-QT-JBLU-2026070001.pdf');
        $this->assertSame($contents, $download->streamedContent());
    }

    public function test_pdf_endpoint_enforces_policy_and_generation_status(): void
    {
        Storage::fake('documents');
        $file = $this->completeFile();
        $quotation = Quotation::query()->findOrFail($file->owner_id);

        $reader = User::factory()->create();
        $reader->assignRole('auditor');
        $this->as($reader)->get(route('quotations.pdf.preview', $quotation))->assertConflict();

        $file->update(['status' => 'failed', 'last_error' => 'secret chrome path']);
        $this->get(route('quotations.pdf.preview', $quotation))->assertStatus(503)
            ->assertDontSee('secret chrome path');

        $this->as(User::factory()->create())->get(route('quotations.pdf.preview', $quotation))->assertForbidden();
    }

    public function test_ready_metadata_never_bypasses_missing_private_file_check(): void
    {
        Storage::fake('documents');
        $file = $this->completeFile();
        $file->update([
            'status' => 'ready', 'size' => 10, 'sha256' => str_repeat('a', 64), 'generated_at' => now('UTC'),
        ]);
        $quotation = Quotation::query()->findOrFail($file->owner_id);
        $quotation->creator->assignRole('quotation-maker');

        $this->as($quotation->creator)->get(route('quotations.pdf.download', $quotation))->assertNotFound();
    }

    private function completeFile(): GeneratedFile
    {
        $maker = User::factory()->create();
        $quotation = $this->quotation($maker);
        $document = Document::query()->create([
            'document_type_id' => DocumentType::query()->first()->getKey(), 'period_year' => 2026,
            'sequence_value' => 1, 'number' => 'QT-JBLU-2026070001', 'issued_at' => now('UTC'),
            'issued_by' => $maker->getKey(), 'title' => $quotation->subject, 'purpose' => 'Quotation PDF test',
            'source_type' => $quotation->getMorphClass(), 'source_id' => $quotation->getKey(),
        ]);
        $quotation->update(['document_id' => $document->getKey(), 'status' => 'complete', 'completed_at' => now('UTC'), 'completed_by' => $maker->getKey()]);

        return GeneratedFile::query()->create([
            'owner_type' => $quotation->getMorphClass(), 'owner_id' => $quotation->getKey(),
            'template_id' => $quotation->template_id, 'kind' => 'quotation_pdf', 'status' => 'queued',
            'disk' => 'documents', 'path' => "quotations/{$quotation->getKey()}/quotation.pdf",
            'mime_type' => 'application/pdf', 'queued_at' => now('UTC'), 'generated_by' => $maker->getKey(),
        ]);
    }

    private function quotation(User $maker): Quotation
    {
        $type = DocumentType::query()->firstOrCreate(['code' => 'QUOTATION'], [
            'name' => 'Quotation', 'number_pattern' => 'QT-JBLU-{YYYY}{MM}{SEQ:4}', 'approval_mode' => 'direct',
        ]);
        $profile = CompanyProfile::query()->create([
            'company_code' => 'JBLU', 'legal_name' => 'PT JBLU', 'display_name' => 'JBLU',
            'address_lines' => ['Jakarta'], 'city' => 'Jakarta', 'postal_code' => '10110', 'country' => 'ID',
        ]);
        $template = DocumentTemplate::query()->create([
            'company_profile_id' => $profile->getKey(), 'type' => 'quotation', 'version' => 1,
            'name' => 'Quotation', 'settings' => ['columns' => [['key' => 'service', 'label' => 'Service', 'value_type' => 'text']]],
        ]);
        $quotation = Quotation::query()->create([
            'template_id' => $template->getKey(), 'quotation_date' => '2026-07-18', 'subject' => 'PDF quotation',
            'customer_name' => 'Customer A', 'customer_address' => 'Jakarta',
            'sender_name' => 'Sales JBLU', 'sender_title' => 'Sales Manager', 'currency' => 'IDR',
            'status' => 'draft', 'approval_mode' => $type->approval_mode, 'item_schema' => $template->settings,
            'created_by' => $maker->getKey(),
        ]);
        $item = $quotation->items()->create(['position' => 1]);
        $item->values()->create(['key' => 'service', 'value' => 'Storage', 'value_type' => 'text', 'position' => 1]);

        return $quotation;
    }

    private function as(User $user): self
    {
        return $this->actingAs($user)->withSession(['office.sso.tokens' => [
            'access_token' => 'encrypted', 'refresh_token' => null,
            'expires_at' => time() + 3600, 'authenticated_at' => time(),
        ]]);
    }
}
