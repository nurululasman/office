<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'queue' => $this->check(function (): void {
                if (! Schema::hasTable('jobs') || ! Schema::hasTable('failed_jobs')) {
                    throw new \RuntimeException('Queue tables are unavailable.');
                }
            }),
            'storage' => $this->check(function (): void {
                $disk = Storage::disk(config('office.documents.disk'));
                $path = '.health/'.bin2hex(random_bytes(12));
                $disk->put($path, 'ok');
                $disk->delete($path);
            }),
        ];
        $healthy = ! in_array('fail', $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'unavailable',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function check(callable $check): string
    {
        try {
            $check();

            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }
}
