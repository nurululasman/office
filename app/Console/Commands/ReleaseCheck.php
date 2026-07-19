<?php

namespace App\Console\Commands;

use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use JsonException;

class ReleaseCheck extends Command
{
    protected $signature = 'office:release:check {--production : Require production config and all external approvals}';

    protected $description = 'Fail-closed Office release readiness gate';

    public function handle(): int
    {
        $production = (bool) $this->option('production');
        $checks = [
            'security gate' => $this->callGate('office:security:check', $production),
            'operational dependencies' => $this->callGate('office:operations:check'),
            'application timezone UTC' => config('app.timezone') === 'UTC',
            'business timezone valid' => in_array(config('office.business_timezone'), DateTimeZone::listIdentifiers(), true),
            'frontend build present' => is_file(public_path('build/manifest.json')),
        ];

        if ($production) {
            $uat = $this->readJson((string) config('operations.uat_evidence_path'));
            $checks += [
                'production environment' => app()->environment('production'),
                'PostgreSQL configured' => config('database.default') === 'pgsql',
                'backup and restore gate' => $this->callGate('office:backup:check', true),
                'UAT evidence readable' => $uat !== null,
                'UAT process owner approved' => ($uat['signoff']['process_owner']['decision'] ?? null) === 'APPROVED',
                'UAT operator approved' => ($uat['signoff']['operator']['decision'] ?? null) === 'APPROVED',
                'UAT has no critical or high defect' => ($uat['open_critical'] ?? null) === 0 && ($uat['open_high'] ?? null) === 0,
                'cutover manifest dry-run' => $this->cutoverDryRun(),
            ];
        }

        foreach ($checks as $label => $passed) {
            $this->{$passed ? 'info' : 'error'}(($passed ? '[PASS] ' : '[FAIL] ').$label);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function callGate(string $command, bool $production = false): bool
    {
        $arguments = $production ? ['--production' => true] : [];

        return Artisan::call($command, $arguments) === self::SUCCESS;
    }

    private function cutoverDryRun(): bool
    {
        $path = (string) config('operations.cutover_manifest_path');

        return is_file($path) && Artisan::call('office:initial-data:apply', ['manifest' => $path]) === self::SUCCESS;
    }

    private function readJson(string $path): ?array
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
}
