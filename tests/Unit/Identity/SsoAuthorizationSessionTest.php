<?php

namespace Tests\Unit\Identity;

use App\Contracts\IdentityProvider;
use App\Data\Identity\AuthorizationRequest;
use App\Data\Identity\SsoProfile;
use App\Data\Identity\SsoTokenSet;
use App\Exceptions\IdentityProviderException;
use App\Services\Identity\SsoAuthorizationSession;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use League\OAuth2\Client\Token\AccessToken;
use Mockery;
use Tests\TestCase;

class SsoAuthorizationSessionTest extends TestCase
{
    public function test_state_is_single_use_and_bound_to_the_pkce_verifier(): void
    {
        $session = new Store('test', new ArraySessionHandler(10));
        $provider = Mockery::mock(IdentityProvider::class);
        $tokens = new SsoTokenSet(new AccessToken(['access_token' => 'token']));
        $profile = new SsoProfile(
            'https://sso.example.test',
            'subject',
            'tenant-office',
            'user@example.test',
            'user',
            null,
        );

        $provider->shouldReceive('authorizationRequest')
            ->once()
            ->withArgs(function (string $state, string $verifier): bool {
                return strlen($state) === 43 && strlen($verifier) === 86;
            })
            ->andReturnUsing(fn (string $state, string $verifier) => new AuthorizationRequest(
                'https://sso.example.test/oauth/authorize?state='.$state,
                $state,
                $verifier,
            ));
        $provider->shouldReceive('exchangeAuthorizationCode')
            ->once()
            ->with('code', Mockery::on(fn (string $verifier): bool => strlen($verifier) === 86))
            ->andReturn($tokens);
        $provider->shouldReceive('profile')->once()->with($tokens)->andReturn($profile);

        $flow = new SsoAuthorizationSession($provider);
        $request = $flow->begin($session);
        $identity = $flow->complete($session, $request->state, 'code');

        $this->assertSame($profile, $identity->profile);
        $this->assertFalse($session->has('office.sso.authorization'));

        $this->expectException(IdentityProviderException::class);
        $flow->complete($session, $request->state, 'code');
    }

    public function test_mismatched_state_is_rejected_and_consumed(): void
    {
        $session = new Store('test', new ArraySessionHandler(10));
        $provider = Mockery::mock(IdentityProvider::class);
        $provider->shouldReceive('authorizationRequest')
            ->once()
            ->andReturnUsing(fn (string $state, string $verifier) => new AuthorizationRequest('https://sso.test', $state, $verifier));

        $flow = new SsoAuthorizationSession($provider);
        $flow->begin($session);

        $this->expectException(IdentityProviderException::class);
        $this->expectExceptionMessage('State SSO tidak valid');

        $flow->complete($session, 'attacker-state', 'code');
    }
}
