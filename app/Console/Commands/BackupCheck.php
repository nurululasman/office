<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use JsonException;

class BackupCheck extends Command
{
    protected $signature = 'office:backup:check {--production : Require a recent successful restore drill}';

    protected $description = 'Validate off-host PostgreSQL, WAL, private-file, and restore evidence';

    public function handle(): int
    {
        $backup = $this->readEvidence((string) config('operations.backup_evidence_path'));
        $checks = [
            'backup evidence readable' => $backup !== null,
            'backup stored off-host' => ($backup['off_host'] ?? false) === true,
            'backup encrypted' => ($backup['encrypted'] ?? false) === true,
            'base backup checksum verified' => ($backup['database']['checksum_verified'] ?? false) === true,
            'base backup recent' => $this->isRecent($backup['database']['completed_at'] ?? null, (int) config('operations.database_backup_max_age_hours') * 60),
            'WAL archive recent' => $this->isRecent($backup['database']['wal_archived_at'] ?? null, (int) config('operations.wal_archive_max_age_minutes')),
            'private file manifest verified' => ($backup['private_files']['manifest_verified'] ?? false) === true,
            'private file backup recent' => $this->isRecent($backup['private_files']['completed_at'] ?? null, (int) config('operations.private_files_max_age_minutes')),
        ];

        if ($this->option('production')) {
            $restore = $this->readEvidence((string) config('operations.restore_evidence_path'));
            $checks += [
                'restore evidence readable' => $restore !== null,
                'restore drill passed' => ($restore['status'] ?? null) === 'passed',
                'restore drill recent' => $this->isRecent($restore['completed_at'] ?? null, (int) config('operations.restore_drill_max_age_days') * 1440),
                'restore met RPO' => isset($restore['actual_rpo_minutes']) && (int) $restore['actual_rpo_minutes'] <= 60,
                'restore met RTO' => isset($restore['actual_rto_minutes']) && (int) $restore['actual_rto_minutes'] <= 240,
            ];
        }

        foreach ($checks as $label => $passed) {
            $this->{$passed ? 'info' : 'error'}(($passed ? '[PASS] ' : '[FAIL] ').$label);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function readEvidence(string $path): ?array
    {
        try {
            if (! is_file($path)) {
                return null;
            }

            $value = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($value) ? $value : null;
        } catch (JsonException) {
            return null;
        }
    }

    private function isRecent(mixed $timestamp, int $maximumAgeMinutes): bool
    {
        if (! is_string($timestamp)) {
            return false;
        }

        try {
            $instant = CarbonImmutable::parse($timestamp);

            return $instant->isPast() && $instant->greaterThanOrEqualTo(now()->subMinutes($maximumAgeMinutes));
        } catch (\Throwable) {
            return false;
        }
    }
}
