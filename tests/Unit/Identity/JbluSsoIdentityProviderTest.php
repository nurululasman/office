<?php

namespace Tests\Unit\Identity;

use App\Data\Identity\PkceCredentials;
use App\Data\Identity\SsoTokenSet;
use App\Exceptions\IdentityProviderException;
use App\Services\Identity\JbluSsoIdentityProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Tests\TestCase;

class JbluSsoIdentityProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sso.base_url', 'https://sso.example.test');
        config()->set('sso.client_id', 'office-client');
        config()->set('sso.client_secret', 'client-secret');
        config()->set('sso.redirect_uri', 'https://office.example.test/auth/callback');
        config()->set('sso.scopes', ['openid', 'profile', 'email']);
        config()->set('sso.tenant_id', 'tenant-office');
    }

    public function test_it_builds_an_authorization_url_with_state_and_pkce_s256(): void
    {
        $credentials = PkceCredentials::generate();
        $provider = new JbluSsoIdentityProvider($this->httpClient([]));

        $request = $provider->authorizationRequest($credentials->state, $credentials->codeVerifier);
        parse_str((string) parse_url($request->url, PHP_URL_QUERY), $query);

        $expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', $credentials->codeVerifier, true)), '+/', '-_'), '=');

        $this->assertSame('https://sso.example.test/oauth/authorize', strtok($request->url, '?'));
        $this->assertSame('code', $query['response_type']);
        $this->assertSame($credentials->state, $query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame($expectedChallenge, $query['code_challenge']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $credentials->state);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{86}$/', $credentials->codeVerifier);
    }

    public function test_it_exchanges_a_code_and_maps_the_internal_profile_contract(): void
    {
        $provider = new JbluSsoIdentityProvider($this->httpClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'token_type' => 'Bearer',
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 3600,
            ], JSON_THROW_ON_ERROR)),
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'user' => [
                    'id' => '019f72be-3540-7f9f-bd15-cf407a3993bf',
                    'username' => 'office.user',
                    'email' => 'OFFICE.USER@EXAMPLE.TEST',
                    'status' => 'active',
                    'avatar_url' => 'https://sso.example.test/avatar.png',
                ],
                'tenant_id' => 'tenant-office',
            ], JSON_THROW_ON_ERROR)),
        ]));

        $tokens = $provider->exchangeAuthorizationCode('authorization-code', str_repeat('v', 64));
        $profile = $provider->profile($tokens);

        $this->assertSame('access-token', $tokens->accessToken());
        $this->assertSame('refresh-token', $tokens->refreshToken());
        $this->assertSame('https://sso.example.test', $profile->issuer);
        $this->assertSame('019f72be-3540-7f9f-bd15-cf407a3993bf', $profile->subject);
        $this->assertSame('office.user@example.test', $profile->email);
        $this->assertSame('tenant-office', $profile->tenantId);
    }

    public function test_it_rejects_a_profile_from_another_tenant(): void
    {
        $provider = new JbluSsoIdentityProvider($this->httpClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'user' => [
                    'id' => '019f72be-3540-7f9f-bd15-cf407a3993bf',
                    'username' => 'office.user',
                    'email' => 'office.user@example.test',
                    'status' => 'active',
                ],
                'tenant_id' => 'another-tenant',
            ], JSON_THROW_ON_ERROR)),
        ]));

        $this->expectException(IdentityProviderException::class);
        $this->expectExceptionMessage('Tenant profil SSO tidak sesuai');

        $provider->profile(new SsoTokenSet(new AccessToken(['access_token' => 'access-token'])));
    }

    public function test_it_revokes_the_refresh_token_with_confidential_client_authentication(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ]));
        $stack->push(Middleware::history($history));
        $provider = new JbluSsoIdentityProvider(new Client(['handler' => $stack]));

        $provider->revoke('refresh-token-secret');

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/oauth/revoke', $request->getUri()->getPath());
        $this->assertStringStartsWith('Basic ', $request->getHeaderLine('Authorization'));
        $this->assertSame('token=refresh-token-secret', (string) $request->getBody());
    }

    /** @param list<Response> $responses */
    private function httpClient(array $responses): Client
    {
        return new Client(['handler' => HandlerStack::create(new MockHandler($responses))]);
    }
}
