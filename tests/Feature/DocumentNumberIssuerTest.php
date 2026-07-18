<?php

namespace Tests\Feature;

use App\Exceptions\DocumentIssuanceException;
use App\Models\DocumentSequence;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Documents\DocumentNumberIssuer;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentNumberIssuerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_it_issues_sequential_numbers_and_audits_inside_the_transaction(): void
    {
        CarbonImmutable::setTestNow('2026-07-18 03:15:00 UTC');
        $issuer = User::factory()->create();
        $type = $this->type('QUOTATION', 'QT-JBLU-{YYYY}{MM}{SEQ:4}');

        $first = $this->issuer()->issue($type, $issuer, 'Quotation A', 'Customer A');
        $second = $this->issuer()->issue($type, $issuer, 'Quotation B', 'Customer B');

        $this->assertSame('QT-JBLU-2026070001', $first->number);
        $this->assertSame('QT-JBLU-2026070002', $second->number);
        $this->assertSame(1, $first->sequence_value);
        $this->assertSame(2, $second->sequence_value);
        $this->assertSame(2026, $second->period_year);
        $this->assertSame('2026-07-18 03:15:00', $first->issued_at->utc()->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('document_sequences', [
            'document_type_id' => $type->getKey(), 'period_year' => 2026, 'last_value' => 2,
        ]);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.issued', 'subject_id' => $first->getKey()]);
    }

    public function test_period_uses_jakarta_time_and_resets_without_a_reset_job(): void
    {
        $issuer = User::factory()->create();
        $type = $this->type('GENERAL', 'DOC-{SEQ:4}');

        CarbonImmutable::setTestNow('2026-12-31 16:59:59 UTC');
        $last2026 = $this->issuer()->issue($type, $issuer, 'Last 2026', 'Register');

        CarbonImmutable::setTestNow('2026-12-31 17:00:00 UTC');
        $first2027 = $this->issuer()->issue($type, $issuer, 'First 2027', 'Register');

        $this->assertSame(2026, $last2026->period_year);
        $this->assertSame('DOC-0001', $last2026->number);
        $this->assertSame(2027, $first2027->period_year);
        $this->assertSame(1, $first2027->sequence_value);
        $this->assertSame('DOC-0001', $first2027->number);
        $this->assertDatabaseCount('document_sequences', 2);
    }

    public function test_sequences_are_isolated_between_document_types(): void
    {
        $issuer = User::factory()->create();
        $quotation = $this->type('QUOTATION', 'QT-{SEQ:4}');
        $contract = $this->type('CONTRACT', 'CTR-{SEQ:4}');

        $this->issuer()->issue($quotation, $issuer, 'Quotation A', 'Customer');
        $quotationTwo = $this->issuer()->issue($quotation, $issuer, 'Quotation B', 'Customer');
        $contractOne = $this->issuer()->issue($contract, $issuer, 'Contract A', 'Customer');

        $this->assertSame('QT-0002', $quotationTwo->number);
        $this->assertSame('CTR-0001', $contractOne->number);
    }

    public function test_retry_for_the_same_source_is_idempotent_and_does_not_increment(): void
    {
        $issuer = User::factory()->create();
        $type = $this->type('QUOTATION', 'QT-{SEQ:4}');
        $source = $this->type('SOURCE', 'SOURCE-{SEQ:4}');

        $first = $this->issuer()->issue($type, $issuer, 'Original', 'Customer', $source);
        $type->update(['is_active' => false]);
        $retry = $this->issuer()->issue($type, $issuer, 'Changed retry payload', 'Other', $source);

        $this->assertTrue($retry->is($first));
        $this->assertSame('Original', $retry->title);
        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseHas('document_sequences', ['last_value' => 1]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_inactive_type_cannot_issue_a_new_number(): void
    {
        $issuer = User::factory()->create();
        $type = $this->type('INACTIVE', 'OFF-{SEQ:4}');
        DB::table('document_types')->where('id', $type->getKey())->update(['is_active' => false]);

        try {
            $this->issuer()->issue($type, $issuer, 'Blocked', 'Customer');
            $this->fail('Inactive type should have been rejected.');
        } catch (DocumentIssuanceException $exception) {
            $this->assertStringContainsString('tidak aktif', $exception->getMessage());
        }

        $this->assertDatabaseCount('document_sequences', 0);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_failure_after_increment_rolls_back_sequence_document_and_audit(): void
    {
        $issuer = User::factory()->create();
        $type = $this->type('INVALID', 'INV-{UNKNOWN}-{SEQ:4}');

        try {
            $this->issuer()->issue($type, $issuer, 'Invalid pattern', 'Customer');
            $this->fail('Invalid stored pattern should have failed.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('document_sequences', 0);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_database_failure_after_increment_also_rolls_back(): void
    {
        $issuer = User::factory()->create();
        $type = $this->type('ROLLBACK', 'RB-{SEQ:4}');
        DB::table('users')->where('id', $issuer->getKey())->delete();

        try {
            $this->issuer()->issue($type, $issuer, 'Foreign key failure', 'Customer');
            $this->fail('Missing issuer foreign key should have failed.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('document_sequences', 0);
        $this->assertDatabaseCount('documents', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_sequence_width_is_a_minimum_and_does_not_wrap(): void
    {
        $issuer = User::factory()->create();
        $type = $this->type('OVERFLOW', 'OV-{SEQ:4}');
        DocumentSequence::query()->create([
            'document_type_id' => $type->getKey(),
            'period_year' => (int) now(config('office.business_timezone'))->format('Y'),
            'last_value' => 9999,
        ]);

        $document = $this->issuer()->issue($type, $issuer, 'Beyond padding', 'Customer');

        $this->assertSame(10000, $document->sequence_value);
        $this->assertSame('OV-10000', $document->number);
    }

    private function issuer(): DocumentNumberIssuer
    {
        return app(DocumentNumberIssuer::class);
    }

    private function type(string $code, string $pattern): DocumentType
    {
        return DocumentType::query()->create([
            'code' => $code,
            'name' => $code,
            'number_pattern' => $pattern,
        ]);
    }
}
