<?php

namespace App\Services\Identity;

use App\Data\Identity\SsoTokenSet;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Crypt;

final class SsoTokenSession
{
    private const KEY = 'office.sso.tokens';

    public function store(Session $session, SsoTokenSet $tokens): void
    {
        $session->put(self::KEY, [
            'access_token' => Crypt::encryptString($tokens->accessToken()),
            'refresh_token' => $tokens->refreshToken() === null
                ? null
                : Crypt::encryptString($tokens->refreshToken()),
            'expires_at' => $tokens->expiresAt(),
            'authenticated_at' => time(),
        ]);
    }

    public function refreshToken(Session $session): ?string
    {
        $encrypted = $session->get(self::KEY.'.refresh_token');

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    public function isValid(Session $session): bool
    {
        $tokens = $session->get(self::KEY);

        if (! is_array($tokens) || ! isset($tokens['authenticated_at']) || ! is_int($tokens['authenticated_at'])) {
            return false;
        }

        $maximumAge = (int) config('sso.session_max_minutes', 480) * 60;
        if (time() - $tokens['authenticated_at'] >= $maximumAge) {
            return false;
        }

        return ! is_int($tokens['expires_at'] ?? null) || time() < $tokens['expires_at'];
    }

    public function forget(Session $session): void
    {
        $session->forget(self::KEY);
    }
}
