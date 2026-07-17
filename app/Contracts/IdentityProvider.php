<?php

namespace App\Contracts;

use App\Data\Identity\AuthorizationRequest;
use App\Data\Identity\SsoProfile;
use App\Data\Identity\SsoTokenSet;

interface IdentityProvider
{
    public function authorizationRequest(string $state, string $codeVerifier): AuthorizationRequest;

    public function exchangeAuthorizationCode(string $code, string $codeVerifier): SsoTokenSet;

    public function profile(SsoTokenSet $tokens): SsoProfile;
}
