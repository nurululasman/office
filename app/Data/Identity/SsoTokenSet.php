<?php

namespace App\Data\Identity;

use League\OAuth2\Client\Token\AccessTokenInterface;

final readonly class SsoTokenSet
{
    public function __construct(private AccessTokenInterface $token) {}

    public function accessToken(): string
    {
        return $this->token->getToken();
    }

    public function refreshToken(): ?string
    {
        return $this->token->getRefreshToken();
    }

    public function expiresAt(): ?int
    {
        return $this->token->getExpires();
    }

    public function leagueToken(): AccessTokenInterface
    {
        return $this->token;
    }
}
