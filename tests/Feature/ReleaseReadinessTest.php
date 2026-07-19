<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_release_gate_passes_repository_and_runtime_dependencies(): void
    {
        $this->assertSame(0, Artisan::call('office:release:check'));
    }

    public function test_production_release_gate_fails_closed_without_external_evidence(): void
    {
        config([
            'operations.backup_evidence_path' => storage_path('framework/testing/missing-backup.json'),
            'operations.restore_evidence_path' => storage_path('framework/testing/missing-restore.json'),
            'operations.uat_evidence_path' => storage_path('framework/testing/missing-uat.json'),
            'operations.cutover_manifest_path' => storage_path('framework/testing/missing-cutover.json'),
        ]);

        $this->assertSame(1, Artisan::call('office:release:check', ['--production' => true]));
    }

    public function test_remote_smoke_is_read_only_and_requires_healthy_https_responses(): void
    {
        Http::fake([
            'https://office.example.test/health/live' => Http::response(
                ['status' => 'ok'],
                200,
                $this->securityHeaders(),
            ),
            'https://office.example.test/health/ready' => Http::response(['status' => 'ok'], 200),
            'https://office.example.test/auth/login' => Http::response('', 302, [
                'Location' => 'https://sso.example.test/oauth/authorize',
            ]),
        ]);

        $this->assertSame(0, Artisan::call('office:smoke', ['--url' => 'https://office.example.test']));

        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

    public function test_smoke_rejects_insecure_url(): void
    {
        $this->assertSame(1, Artisan::call('office:smoke', ['--url' => 'http://office.example.test']));
        Http::assertNothingSent();
    }

    private function securityHeaders(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        ];
    }
}
