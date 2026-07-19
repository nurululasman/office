<?php

namespace Tests\Feature;

use App\Contracts\IdentityProvider;
use App\Data\Identity\AuthorizationRequest;
use App\Data\Identity\SsoProfile;
use App\Data\Identity\SsoTokenSet;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Identity\SsoUserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use Tests\TestCase;

class SsoAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_sso_callback_provisions_user_syncs_profile_and_logout_revokes_token(): void
    {
        $provider = Mockery::mock(IdentityProvider::class);
        $this->app->instance(IdentityProvider::class, $provider);

        $profile = new SsoProfile(
            issuer: 'https://sso.example.test',
            subject: '019f72be-3540-7f9f-bd15-cf407a3993bf',
            tenantId: 'tenant-office',
            email: 'office.user@example.test',
            name: 'office.user',
            avatarUrl: 'https://sso.example.test/avatar.png',
        );
        $tokens = new SsoTokenSet(new AccessToken([
            'access_token' => 'access-token-secret',
            'refresh_token' => 'refresh-token-secret',
            'expires' => time() + 3600,
        ]));

        $provider->shouldReceive('authorizationRequest')
            ->once()
            ->andReturnUsing(fn (string $state, string $verifier) => new AuthorizationRequest(
                'https://sso.example.test/oauth/authorize?state='.$state,
                $state,
                $verifier,
            ));
        $provider->shouldReceive('exchangeAuthorizationCode')->once()->andReturn($tokens);
        $provider->shouldReceive('profile')->once()->with($tokens)->andReturn($profile);
        $provider->shouldReceive('revoke')->once()->with('refresh-token-secret');

        $loginResponse = $this->get('/auth/login')->assertRedirect();
        parse_str((string) parse_url($loginResponse->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->get('/auth/callback?'.http_build_query([
            'state' => $query['state'],
            'code' => 'authorization-code',
        ]))->assertRedirect(route('office.home'));

        $user = User::query()->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($profile->subject, $user->sso_subject);
        $this->assertSame($profile->email, $user->email);
        $this->assertNull($user->password);
        $this->assertNotSame('access-token-secret', session('office.sso.tokens.access_token'));
        $this->assertNotSame('refresh-token-secret', session('office.sso.tokens.refresh_token'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'auth.login.succeeded',
        ]);

        $this->get('/office')->assertOk();
        $this->post('/logout')->assertRedirect(route('welcome'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'action' => 'auth.logout']);
    }

    public function test_existing_profile_is_updated_but_local_inactive_user_is_rejected(): void
    {
        $user = User::factory()->create([
            'sso_issuer' => 'https://sso.example.test',
            'sso_subject' => '019f72be-3540-7f9f-bd15-cf407a3993bf',
            'name' => 'old-name',
            'is_active' => false,
        ]);
        $provider = Mockery::mock(IdentityProvider::class);
        $this->app->instance(IdentityProvider::class, $provider);
        $tokens = new SsoTokenSet(new AccessToken(['access_token' => 'access-token']));

        $provider->shouldReceive('authorizationRequest')
            ->once()
            ->andReturnUsing(fn (string $state, string $verifier) => new AuthorizationRequest(
                'https://sso.example.test/oauth/authorize?state='.$state,
                $state,
                $verifier,
            ));
        $provider->shouldReceive('exchangeAuthorizationCode')->once()->andReturn($tokens);
        $provider->shouldReceive('profile')->once()->andReturn(new SsoProfile(
            'https://sso.example.test',
            $user->sso_subject,
            'tenant-office',
            'new.email@example.test',
            'new-name',
            null,
        ));

        $loginResponse = $this->get('/auth/login');
        parse_str((string) parse_url($loginResponse->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->get('/auth/callback?'.http_build_query(['state' => $query['state'], 'code' => 'code']))
            ->assertRedirect(route('welcome'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::query()->where('action', 'auth.login.failed')->count());
        $this->assertSame('old-name', $user->fresh()->name);
    }

    public function test_existing_active_shadow_user_profile_is_synchronized_without_changing_identity(): void
    {
        $user = User::factory()->create([
            'sso_issuer' => 'https://sso.example.test',
            'sso_subject' => '019f72be-3540-7f9f-bd15-cf407a3993bf',
            'name' => 'old-name',
            'email' => 'old.email@example.test',
        ]);

        $updated = app(SsoUserProvisioner::class)->provision(new SsoProfile(
            issuer: $user->sso_issuer,
            subject: $user->sso_subject,
            tenantId: 'tenant-office',
            email: 'new.email@example.test',
            name: 'new-name',
            avatarUrl: 'https://sso.example.test/new-avatar.png',
        ));

        $this->assertTrue($user->is($updated));
        $this->assertSame('new-name', $updated->name);
        $this->assertSame('new.email@example.test', $updated->email);
        $this->assertSame('https://sso.example.test/new-avatar.png', $updated->avatar_url);
        $this->assertNotNull($updated->last_login_at);
    }

    public function test_invalid_or_expired_local_sso_session_logs_the_user_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                'office.sso.tokens' => [
                    'access_token' => 'encrypted',
                    'refresh_token' => null,
                    'expires_at' => time() - 1,
                    'authenticated_at' => time() - 60,
                ],
            ])
            ->get('/office')
            ->assertRedirect(route('welcome'))
            ->assertSessionHasErrors('sso');

        $this->assertGuest();
        $this->assertSame(1, AuditLog::query()->where('action', 'auth.session.expired')->count());
    }

    public function test_guest_is_redirected_to_sso_login_from_protected_route(): void
    {
        $this->get('/office')->assertRedirect(route('login'));
    }

    public function test_welcome_redirects_guest_to_login(): void
    {
        $this->get(route('welcome'))->assertRedirect(route('login'));
    }

    public function test_welcome_redirects_authenticated_user_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession([
                'office.sso.tokens' => [
                    'access_token' => 'encrypted',
                    'refresh_token' => null,
                    'expires_at' => time() + 3600,
                    'authenticated_at' => time(),
                ],
            ])
            ->get(route('welcome'))
            ->assertRedirect(route('office.home'));
    }
}
