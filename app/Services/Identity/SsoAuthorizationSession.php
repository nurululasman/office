<?php

namespace App\Services\Identity;

use App\Contracts\IdentityProvider;
use App\Data\Identity\AuthenticatedIdentity;
use App\Data\Identity\AuthorizationRequest;
use App\Data\Identity\PkceCredentials;
use App\Exceptions\IdentityProviderException;
use Illuminate\Contracts\Session\Session;

final readonly class SsoAuthorizationSession
{
    private const SESSION_KEY = 'office.sso.authorization';

    private const MAX_AGE_SECONDS = 600;

    public function __construct(private IdentityProvider $identityProvider) {}

    public function begin(Session $session): AuthorizationRequest
    {
        $credentials = PkceCredentials::generate();
        $session->put(self::SESSION_KEY, [
            'state' => $credentials->state,
            'code_verifier' => $credentials->codeVerifier,
            'issued_at' => $credentials->issuedAt,
        ]);

        return $this->identityProvider->authorizationRequest(
            $credentials->state,
            $credentials->codeVerifier,
        );
    }

    public function complete(Session $session, string $state, string $code): AuthenticatedIdentity
    {
        $pending = $session->pull(self::SESSION_KEY);

        if (! is_array($pending)
            || ! isset($pending['state'], $pending['code_verifier'], $pending['issued_at'])
            || ! is_string($pending['state'])
            || ! is_string($pending['code_verifier'])
            || ! is_int($pending['issued_at'])
            || ! hash_equals($pending['state'], $state)
            || time() - $pending['issued_at'] > self::MAX_AGE_SECONDS
        ) {
            throw new IdentityProviderException('State SSO tidak valid atau telah kedaluwarsa.');
        }

        if ($code === '') {
            throw new IdentityProviderException('Authorization code SSO tidak tersedia.');
        }

        $tokens = $this->identityProvider->exchangeAuthorizationCode($code, $pending['code_verifier']);

        return new AuthenticatedIdentity(
            profile: $this->identityProvider->profile($tokens),
            tokens: $tokens,
        );
    }
}
