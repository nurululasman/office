<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DocumentTemplates\LegacyQuotationTemplateMigrator;
use Illuminate\Console\Command;
use Throwable;

class MigrateLegacyQuotationTemplates extends Command
{
    protected $signature = 'office:quotation-templates:migrate-legacy
        {--apply : Create and activate the WYSIWYG template version}
        {--rollback : Reactivate the archived legacy version}
        {--actor= : Active system administrator user ID or email}';

    protected $description = 'Dry-run, apply, or roll back the legacy quotation template conversion';

    public function handle(LegacyQuotationTemplateMigrator $migrator): int
    {
        try {
            if ($this->option('apply') && $this->option('rollback')) {
                throw new \LogicException('Pilih salah satu --apply atau --rollback.');
            }

            if (! $this->option('apply') && ! $this->option('rollback')) {
                $plan = $migrator->plan();
                $this->table(
                    ['ID', 'Name', 'Version', 'Status', 'Columns', 'Quotations', 'Files'],
                    array_map(fn (array $row): array => array_values($row), $plan['candidates']),
                );
                $this->info(sprintf(
                    '[DRY-RUN] %d candidate, %d already migrated.',
                    count($plan['candidates']),
                    count($plan['migrated']),
                ));
                $this->info('[PASS] No data changed.');

                return self::SUCCESS;
            }

            $actor = $this->actor();
            if ($this->option('rollback')) {
                $count = $migrator->rollback($actor);
                $this->info("[PASS] Rollback selesai: {$count} template family dipulihkan.");

                return self::SUCCESS;
            }

            $templates = $migrator->migrate($actor);
            $this->info(sprintf('[PASS] Migrasi selesai: %d template version dibuat dan diaktifkan.', count($templates)));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('[FAIL] '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function actor(): User
    {
        $identity = trim((string) $this->option('actor'));
        if ($identity === '') {
            throw new \LogicException('Opsi --actor wajib untuk apply atau rollback.');
        }

        return User::query()
            ->where('id', $identity)
            ->orWhere('email', $identity)
            ->firstOrFail();
    }
}
