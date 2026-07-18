<?php

namespace App\Services\Identity;

use App\Contracts\IdentityProvider;
use App\Data\Identity\AuthorizationRequest;
use App\Data\Identity\SsoProfile;
use App\Data\Identity\SsoTokenSet;
use App\Exceptions\IdentityProviderException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Throwable;

final class JbluSsoIdentityProvider implements IdentityProvider
{
    private GenericProvider $provider;

    private ClientInterface $httpClient;

    public function __construct(?ClientInterface $httpClient = null)
    {
        $baseUrl = (string) config('sso.base_url');
        $this->assertConfigured($baseUrl);

        $this->httpClient = $httpClient ?? new Client;
        $collaborators = ['httpClient' => $this->httpClient];
        $this->provider = new GenericProvider([
            'clientId' => config('sso.client_id'),
            'clientSecret' => config('sso.client_secret'),
            'redirectUri' => config('sso.redirect_uri'),
            'urlAuthorize' => $baseUrl.config('sso.paths.authorize'),
            'urlAccessToken' => $baseUrl.config('sso.paths.token'),
            'urlResourceOwnerDetails' => $baseUrl.config('sso.paths.profile'),
            'scopes' => config('sso.scopes'),
            'scopeSeparator' => ' ',
        ], $collaborators);
    }

    public function authorizationRequest(string $state, string $codeVerifier): AuthorizationRequest
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $url = $this->provider->getAuthorizationUrl([
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        return new AuthorizationRequest($url, $state, $codeVerifier);
    }

    public function exchangeAuthorizationCode(string $code, string $codeVerifier): SsoTokenSet
    {
        try {
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $code,
                'code_verifier' => $codeVerifier,
            ]);
        } catch (Throwable $exception) {
            throw new IdentityProviderException('SSO menolak pertukaran authorization code.', previous: $exception);
        }

        return new SsoTokenSet($token);
    }

    public function profile(SsoTokenSet $tokens): SsoProfile
    {
        try {
            $payload = $this->resourceOwnerPayload($this->provider->getResourceOwner(
                new AccessToken(['access_token' => $tokens->accessToken()]),
            ));
        } catch (Throwable $exception) {
            throw new IdentityProviderException('Profil pengguna tidak dapat diambil dari SSO.', previous: $exception);
        }

        $user = $payload['user'] ?? null;
        $tenantId = $payload['tenant_id'] ?? null;
        $expectedTenantId = config('sso.tenant_id');

        if (! is_array($user) || ! is_string($tenantId) || $tenantId === '') {
            throw new IdentityProviderException('Respons profil SSO tidak lengkap.');
        }

        if (! is_string($expectedTenantId) || $expectedTenantId === '' || ! hash_equals($expectedTenantId, $tenantId)) {
            throw new IdentityProviderException('Tenant profil SSO tidak sesuai dengan tenant Office.');
        }

        if (($user['status'] ?? null) !== 'active') {
            throw new IdentityProviderException('Akun SSO tidak aktif.');
        }

        foreach (['id', 'email', 'username'] as $field) {
            if (! isset($user[$field]) || ! is_string($user[$field]) || trim($user[$field]) === '') {
                throw new IdentityProviderException("Field user.{$field} tidak tersedia pada profil SSO.");
            }
        }

        return new SsoProfile(
            issuer: (string) config('sso.base_url'),
            subject: $user['id'],
            tenantId: $tenantId,
            email: mb_strtolower(trim($user['email'])),
            name: trim($user['username']),
            avatarUrl: isset($user['avatar_url']) && is_string($user['avatar_url']) ? $user['avatar_url'] : null,
        );
    }

    public function revoke(string $token): void
    {
        try {
            $this->httpClient->request('POST', config('sso.base_url').config('sso.paths.revoke'), [
                'auth' => [config('sso.client_id'), config('sso.client_secret')],
                'form_params' => ['token' => $token],
                'headers' => ['Accept' => 'application/json'],
            ]);
        } catch (Throwable $exception) {
            throw new IdentityProviderException('Token SSO tidak dapat dicabut.', previous: $exception);
        }
    }

    private function assertConfigured(string $baseUrl): void
    {
        foreach (['base_url', 'client_id', 'client_secret', 'redirect_uri', 'tenant_id'] as $key) {
            if (! is_string(config("sso.{$key}")) || config("sso.{$key}") === '') {
                throw new IdentityProviderException("Konfigurasi sso.{$key} wajib diisi.");
            }
        }

        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new IdentityProviderException('Konfigurasi sso.base_url harus berupa URL valid.');
        }
    }

    /** @return array<string, mixed> */
    private function resourceOwnerPayload(ResourceOwnerInterface $resourceOwner): array
    {
        if (! $resourceOwner instanceof GenericResourceOwner) {
            throw new IdentityProviderException('Tipe respons profil SSO tidak didukung.');
        }

        return $resourceOwner->toArray();
    }
}
