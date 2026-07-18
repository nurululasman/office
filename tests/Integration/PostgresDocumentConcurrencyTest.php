<?php

namespace Tests\Integration;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\DocumentType;
use App\Models\User;
use App\Services\Documents\DocumentNumberIssuer;
use App\Services\Documents\DocumentVoidService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostgresDocumentConcurrencyTest extends TestCase
{
    /** @var list<string> */
    private array $typeIds = [];

    /** @var list<int> */
    private array $userIds = [];

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
        CarbonImmutable::setTestNow();
        $this->cleanupFixtures();
        parent::tearDown();
    }

    public function test_concurrent_requests_for_one_type_receive_unique_contiguous_numbers(): void
    {
        $user = $this->user();
        $type = $this->type('SAME');
        $jobs = [];

        for ($index = 1; $index <= 12; $index++) {
            $jobs[] = [$type->getKey(), $user->getKey(), "Concurrent {$index}", 'Same type concurrency'];
        }

        $results = $this->runConcurrently($jobs);
        $sequences = collect($results)->pluck('sequence_value')->sort()->values()->all();
        $numbers = collect($results)->pluck('number');

        $this->assertSame(range(1, 12), $sequences);
        $this->assertCount(12, $numbers->unique());
        $this->assertSame(12, Document::query()->where('document_type_id', $type->getKey())->count());
        $this->assertSame(12, DocumentSequence::query()->where('document_type_id', $type->getKey())->value('last_value'));
    }

    public function test_different_types_progress_independently_under_parallel_load(): void
    {
        $user = $this->user();
        $typeA = $this->type('PAR_A');
        $typeB = $this->type('PAR_B');
        $jobs = [];

        for ($index = 1; $index <= 6; $index++) {
            $jobs[] = [$typeA->getKey(), $user->getKey(), "A {$index}", 'Parallel type A'];
            $jobs[] = [$typeB->getKey(), $user->getKey(), "B {$index}", 'Parallel type B'];
        }

        $results = collect($this->runConcurrently($jobs))->groupBy('document_type_id');

        $this->assertSame(range(1, 6), $results[$typeA->getKey()]->pluck('sequence_value')->sort()->values()->all());
        $this->assertSame(range(1, 6), $results[$typeB->getKey()]->pluck('sequence_value')->sort()->values()->all());
        $this->assertSame(6, DocumentSequence::query()->where('document_type_id', $typeA->getKey())->value('last_value'));
        $this->assertSame(6, DocumentSequence::query()->where('document_type_id', $typeB->getKey())->value('last_value'));
    }

    public function test_concurrent_retry_across_types_returns_one_document_for_the_source(): void
    {
        $user = $this->user();
        $typeA = $this->type('IDEM_A');
        $typeB = $this->type('IDEM_B');
        $source = $this->type('SOURCE');
        $jobs = [];

        for ($index = 1; $index <= 10; $index++) {
            $type = $index % 2 === 0 ? $typeA : $typeB;
            $jobs[] = [$type->getKey(), $user->getKey(), "Retry {$index}", 'Same source retry', $source->getKey()];
        }

        $results = collect($this->runConcurrently($jobs));

        $this->assertCount(1, $results->pluck('id')->unique());
        $this->assertCount(1, $results->pluck('number')->unique());
        $this->assertSame(1, Document::query()->where('source_id', $source->getKey())->count());
        $this->assertSame(1, (int) DocumentSequence::query()->whereIn('document_type_id', [$typeA->getKey(), $typeB->getKey()])->sum('last_value'));
        $this->assertSame(1, AuditLog::query()->where('action', 'document.issued')->whereIn('subject_id', $results->pluck('id'))->count());
    }

    public function test_year_rollover_rollback_and_void_never_reuse_a_number(): void
    {
        $user = $this->user();
        $type = $this->type('LIFECYCLE', 'LC-{YYYY}-{SEQ:4}');

        CarbonImmutable::setTestNow('2026-12-31 16:59:59 UTC');
        $last2026 = app(DocumentNumberIssuer::class)->issue($type, $user, 'Last 2026', 'Lifecycle');
        CarbonImmutable::setTestNow('2026-12-31 17:00:00 UTC');
        $first2027 = app(DocumentNumberIssuer::class)->issue($type, $user, 'First 2027', 'Lifecycle');
        app(DocumentVoidService::class)->void($first2027, $user, 'Lifecycle void verification');
        $second2027 = app(DocumentNumberIssuer::class)->issue($type, $user, 'Second 2027', 'Lifecycle');

        $invalid = $this->type('ROLLBACK', 'RB-{UNKNOWN}-{SEQ:4}');
        try {
            app(DocumentNumberIssuer::class)->issue($invalid, $user, 'Rollback', 'Invalid pattern');
            $this->fail('Invalid pattern should roll back.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(2026, $last2026->period_year);
        $this->assertSame(1, $last2026->sequence_value);
        $this->assertSame(2027, $first2027->period_year);
        $this->assertSame(1, $first2027->sequence_value);
        $this->assertSame(2, $second2027->sequence_value);
        $this->assertNotSame($first2027->number, $second2027->number);
        $this->assertNotNull($first2027->fresh()->voided_at);
        $this->assertFalse(DocumentSequence::query()->where('document_type_id', $invalid->getKey())->exists());
        $this->assertFalse(Document::query()->where('document_type_id', $invalid->getKey())->exists());
    }

    /**
     * @param  list<array{0: string, 1: int, 2: string, 3: string, 4?: string}>  $jobs
     * @return list<array{id: string, number: string, sequence_value: int, document_type_id: string}>
     */
    private function runConcurrently(array $jobs): array
    {
        $processes = array_map(function (array $arguments): Process {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/issue_document_worker.php'),
                ...array_map(fn (mixed $argument): string => (string) $argument, $arguments),
            ], base_path(), timeout: 45);
            $process->start();

            return $process;
        }, $jobs);

        return array_map(function (Process $process): array {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

            /** @var array{id: string, number: string, sequence_value: int, document_type_id: string} */
            return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }, $processes);
    }

    private function type(string $prefix, ?string $pattern = null): DocumentType
    {
        $suffix = strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 10));
        $type = DocumentType::query()->create([
            'code' => "T_{$prefix}_{$suffix}",
            'name' => "OFF0206 {$prefix} {$suffix}",
            'number_pattern' => $pattern ?? "{$prefix}-{YYYY}-{SEQ:4}",
        ]);
        $this->typeIds[] = $type->getKey();

        return $type;
    }

    private function user(): User
    {
        $user = User::factory()->create(['name' => 'OFF0206 Concurrency Fixture']);
        $this->userIds[] = $user->getKey();

        return $user;
    }

    private function cleanupFixtures(): void
    {
        if ($this->typeIds === [] && $this->userIds === []) {
            return;
        }

        $documentIds = Document::query()
            ->whereIn('document_type_id', $this->typeIds)
            ->orWhereIn('source_id', $this->typeIds)
            ->pluck('id');

        AuditLog::query()->where('subject_type', (new Document)->getMorphClass())->whereIn('subject_id', $documentIds)->delete();
        Document::query()->whereIn('id', $documentIds)->delete();
        DocumentSequence::query()->whereIn('document_type_id', $this->typeIds)->delete();
        DocumentType::query()->whereIn('id', $this->typeIds)->delete();
        User::query()->whereIn('id', $this->userIds)->delete();

        $this->typeIds = [];
        $this->userIds = [];
    }
}
