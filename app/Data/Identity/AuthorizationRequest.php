<?php

namespace App\Data\Identity;

final readonly class AuthorizationRequest
{
    public function __construct(
        public string $url,
        public string $state,
        public string $codeVerifier,
    ) {}
}
