<?php

namespace Tests\Integration;

use App\Models\AuditLog;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgresQuotationConcurrencyTest extends TestCase
{
    private ?Quotation $quotation = null;

    private ?DocumentTemplate $template = null;

    private ?CompanyProfile $profile = null;

    private ?DocumentType $type = null;

    private ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('OFFICE_RUN_PG_CONCURRENCY_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set OFFICE_RUN_PG_CONCURRENCY_TESTS=true untuk menjalankan gate PostgreSQL.');
        }
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Concurrency test wajib menggunakan PostgreSQL.');
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
        parent::tearDown();
    }

    public function test_concurrent_direct_completion_returns_one_number_and_one_success_audit(): void
    {
        $this->createFixtures();
        $processes = [];

        for ($index = 0; $index < 8; $index++) {
            $process = new Process([
                PHP_BINARY, base_path('tests/Support/complete_quotation_worker.php'),
                $this->quotation->getKey(), (string) $this->user->getKey(), '0', $this->type->code,
            ], base_path(), timeout: 45);
            $process->start();
            $processes[] = $process;
        }

        $results = collect($processes)->map(function (Process $process): array {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

            return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        });

        $this->assertCount(1, $results->pluck('quotation_id')->unique());
        $this->assertCount(1, $results->pluck('document_id')->unique());
        $this->assertCount(1, $results->pluck('number')->unique());
        $this->assertSame(['complete'], $results->pluck('status')->unique()->values()->all());
        $this->assertSame(1, Document::query()->where('source_id', $this->quotation->getKey())->count());
        $this->assertSame(1, DocumentSequence::query()->where('document_type_id', $this->type->getKey())->value('last_value'));
        $this->assertSame(1, AuditLog::query()->where('subject_id', $this->quotation->getKey())->where('action', 'quotation.approval_bypassed')->count());
        $this->assertSame(1, AuditLog::query()->where('subject_id', $this->quotation->getKey())->where('action', 'quotation.completed')->count());
    }

    private function createFixtures(): void
    {
        $suffix = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 10));
        $this->user = User::factory()->create(['name' => 'OFF0306 Concurrency Fixture']);
        $this->type = DocumentType::query()->create([
            'code' => 'Q_'.$suffix, 'name' => 'OFF0306 Quotation '.$suffix,
            'number_pattern' => 'QT-'.$suffix.'-{YYYY}{MM}{SEQ:4}', 'approval_mode' => 'direct',
        ]);
        $this->profile = CompanyProfile::query()->create([
            'company_code' => 'Q'.$suffix, 'legal_name' => 'OFF0306 Company', 'display_name' => 'OFF0306',
            'address_lines' => ['Jakarta'], 'city' => 'Jakarta', 'postal_code' => '10110', 'country' => 'ID',
        ]);
        $this->template = DocumentTemplate::query()->create([
            'company_profile_id' => $this->profile->getKey(), 'type' => 'quotation_off0306_'.$suffix,
            'version' => 1, 'name' => 'OFF0306 Template', 'settings' => ['columns' => []],
        ]);
        $this->quotation = Quotation::query()->create([
            'created_by' => $this->user->getKey(), 'template_id' => $this->template->getKey(),
            'status' => 'draft', 'approval_mode' => 'direct', 'quotation_date' => '2026-07-18',
            'subject' => 'OFF0306 Concurrent Quotation', 'customer_name' => 'Concurrency Customer',
            'customer_address' => 'Jakarta', 'sender_name' => 'OFF0306', 'sender_title' => 'Tester',
            'item_schema' => ['columns' => []], 'currency' => 'IDR',
        ]);
    }

    private function cleanupFixtures(): void
    {
        if (! $this->quotation) {
            return;
        }

        $documentIds = Document::query()->where('source_id', $this->quotation->getKey())->pluck('id');
        $auditSubjectIds = $documentIds->concat([$this->quotation->getKey()]);
        AuditLog::query()->whereIn('subject_id', $auditSubjectIds)->delete();
        Quotation::query()->whereKey($this->quotation->getKey())->delete();
        Document::query()->whereIn('id', $documentIds)->delete();
        DocumentSequence::query()->where('document_type_id', $this->type->getKey())->delete();
        DocumentTemplate::query()->whereKey($this->template->getKey())->delete();
        CompanyProfile::query()->whereKey($this->profile->getKey())->delete();
        DocumentType::query()->whereKey($this->type->getKey())->delete();
        User::query()->whereKey($this->user->getKey())->delete();
    }
}
