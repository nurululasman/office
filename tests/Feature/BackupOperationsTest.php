<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupOperationsTest extends TestCase
{
    private string $backupEvidence;

    private string $restoreEvidence;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-19T00:00:00Z');
        $this->backupEvidence = storage_path('framework/testing/backup-status.json');
        $this->restoreEvidence = storage_path('framework/testing/restore-drill.json');
        @unlink($this->backupEvidence);
        @unlink($this->restoreEvidence);
        config([
            'operations.backup_evidence_path' => $this->backupEvidence,
            'operations.restore_evidence_path' => $this->restoreEvidence,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->backupEvidence);
        @unlink($this->restoreEvidence);
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_production_gate_accepts_recent_verified_backup_and_restore_evidence(): void
    {
        $this->writeEvidence();

        $this->assertSame(0, Artisan::call('office:backup:check', ['--production' => true]));
        $this->assertStringContainsString('[PASS] restore met RTO', Artisan::output());
    }

    public function test_gate_fails_closed_for_stale_wal(): void
    {
        $this->writeEvidence(walArchivedAt: '2026-07-18T22:59:00Z', includeRestore: false);

        $this->assertSame(1, Artisan::call('office:backup:check', ['--production' => true]));
        $this->assertStringContainsString('[FAIL] WAL archive recent', Artisan::output());
    }

    public function test_production_gate_fails_closed_without_restore_evidence(): void
    {
        $this->writeEvidence(includeRestore: false);

        $this->assertSame(1, Artisan::call('office:backup:check', ['--production' => true]));
    }

    private function writeEvidence(string $walArchivedAt = '2026-07-18T23:30:00Z', bool $includeRestore = true): void
    {
        file_put_contents($this->backupEvidence, json_encode([
            'off_host' => true,
            'encrypted' => true,
            'database' => ['completed_at' => '2026-07-18T23:00:00Z', 'wal_archived_at' => $walArchivedAt, 'checksum_verified' => true],
            'private_files' => ['completed_at' => '2026-07-18T23:30:00Z', 'manifest_verified' => true],
        ], JSON_THROW_ON_ERROR));

        if ($includeRestore) {
            file_put_contents($this->restoreEvidence, json_encode([
                'completed_at' => '2026-07-01T00:00:00Z', 'status' => 'passed',
                'actual_rpo_minutes' => 25, 'actual_rto_minutes' => 90,
            ], JSON_THROW_ON_ERROR));
        }
    }
}
