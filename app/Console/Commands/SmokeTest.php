<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class SmokeTest extends Command
{
    protected $signature = 'office:smoke {--url= : Deployed Office base URL}';

    protected $description = 'Read-only post-deployment Office HTTP smoke test';

    public function handle(): int
    {
        $baseUrl = rtrim((string) ($this->option('url') ?: config('app.url')), '/');
        if (! str_starts_with($baseUrl, 'https://')) {
            $this->error('[FAIL] Smoke URL wajib HTTPS.');

            return self::FAILURE;
        }

        try {
            $live = Http::timeout(10)->get($baseUrl.'/health/live');
            $ready = Http::timeout(10)->get($baseUrl.'/health/ready');
            $login = Http::timeout(10)->withoutRedirecting()->get($baseUrl.'/auth/login');
            $checks = [
                'liveness' => $live->ok() && $live->json('status') === 'ok',
                'readiness' => $ready->ok() && $ready->json('status') === 'ok',
                'SSO login redirects' => $login->redirect() && $this->isHttpsLocation($login),
                'nosniff header' => $live->header('X-Content-Type-Options') === 'nosniff',
                'anti-frame header' => $live->header('X-Frame-Options') === 'DENY',
                'HSTS header' => str_contains($live->header('Strict-Transport-Security'), 'max-age='),
            ];
        } catch (Throwable) {
            $this->error('[FAIL] Deployment tidak dapat dijangkau.');

            return self::FAILURE;
        }

        foreach ($checks as $label => $passed) {
            $this->{$passed ? 'info' : 'error'}(($passed ? '[PASS] ' : '[FAIL] ').$label);
        }

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }

    private function isHttpsLocation(Response $response): bool
    {
        return str_starts_with($response->header('Location'), 'https://');
    }
}
