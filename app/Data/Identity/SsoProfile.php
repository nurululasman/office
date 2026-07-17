<?php

namespace App\Data\Identity;

final readonly class SsoProfile
{
    public function __construct(
        public string $issuer,
        public string $subject,
        public string $tenantId,
        public string $email,
        public string $name,
        public ?string $avatarUrl,
    ) {}
}
