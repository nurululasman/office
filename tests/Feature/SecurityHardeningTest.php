<?php

namespace Tests\Feature;

use App\Contracts\IdentityProvider;
use App\Data\Identity\AuthorizationRequest;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_web_responses_include_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy', "base-uri 'self'; frame-ancestors 'none'; object-src 'none'")
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://office.example.test/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_sensitive_routes_use_named_rate_limits_and_web_csrf_stack(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertContains('throttle:sso-login', $routes->getByName('login')->gatherMiddleware());
        $this->assertContains('throttle:sso-callback', $routes->getByName('auth.callback')->gatherMiddleware());
        $this->assertContains('throttle:office-preview', $routes->getByName('quotations.pdf.download')->gatherMiddleware());
        $this->assertContains('throttle:office-mutation', $routes->getByName('quotations.approve')->gatherMiddleware());
        $this->assertContains('web', $routes->getByName('quotations.approve')->gatherMiddleware());
    }

    public function test_sso_login_rate_limit_is_enforced(): void
    {
        $provider = Mockery::mock(IdentityProvider::class);
        $this->app->instance(IdentityProvider::class, $provider);
        $provider->shouldReceive('authorizationRequest')->times(10)
            ->andReturnUsing(fn (string $state, string $verifier) => new AuthorizationRequest(
                'https://sso.example.test/oauth/authorize?state='.$state,
                $state,
                $verifier,
            ));

        foreach (range(1, 10) as $_) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->get('/auth/login')
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get('/auth/login')
            ->assertTooManyRequests();
    }

    public function test_production_security_gate_passes_safe_configuration_and_fails_closed(): void
    {
        config([
            'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'app.url' => 'https://office.example.test',
            'session.encrypt' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.secure' => true,
            'sso.base_url' => 'https://sso.example.test',
            'sso.client_secret' => 'test-secret-at-least-16-characters',
            'sso.redirect_uri' => 'https://office.example.test/auth/callback',
            'sso.tenant_id' => 'tenant-office',
            'office.documents.disk' => 'documents',
            'filesystems.disks.documents.serve' => false,
            'queue.connections.database.after_commit' => true,
        ]);

        $this->assertSame(0, Artisan::call('office:security:check', ['--production' => true]));
        $this->assertStringNotContainsString('test-secret-at-least-16-characters', Artisan::output());

        config(['app.debug' => true]);

        $this->assertSame(1, Artisan::call('office:security:check', ['--production' => true]));
        $this->assertStringContainsString('[FAIL] APP_DEBUG disabled', Artisan::output());
        $this->assertStringNotContainsString('test-secret-at-least-16-characters', Artisan::output());
    }
}
