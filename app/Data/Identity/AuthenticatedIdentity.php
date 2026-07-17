<?php

namespace App\Data\Identity;

final readonly class AuthenticatedIdentity
{
    public function __construct(
        public SsoProfile $profile,
        public SsoTokenSet $tokens,
    ) {}
}
