<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OperationsCheck extends Command
{
    protected $signature = 'office:operations:check';

    protected $description = 'Read-only monitoring gate for database, queue, failed jobs, and private storage';

    public function handle(): int
    {
        try {
            DB::select('select 1');
            $jobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
            $disk = Storage::disk(config('office.documents.disk'));
            $probe = '.health/'.bin2hex(random_bytes(12));
            $storage = $disk->put($probe, 'ok');
            $disk->delete($probe);
        } catch (Throwable) {
            $this->error('[FAIL] operational dependency unavailable');

            return self::FAILURE;
        }

        $checks = [
            'queue tables available' => $jobs !== null && $failed !== null,
            'pending queue below alert threshold' => $jobs !== null && $jobs < (int) config('operations.queue_pending_alert'),
            'failed queue below alert threshold' => $failed !== null && $failed < (int) config('operations.queue_failed_alert'),
            'private storage writable' => $storage === true,
        ];

        foreach ($checks as $label => $passed) {
            $this->{$passed ? 'info' : 'error'}(($passed ? '[PASS] ' : '[FAIL] ').$label);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
