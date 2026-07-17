<?php

namespace App\Data\Identity;

final readonly class PkceCredentials
{
    public function __construct(
        public string $state,
        public string $codeVerifier,
        public int $issuedAt,
    ) {}

    public static function generate(): self
    {
        return new self(
            state: self::base64Url(random_bytes(32)),
            codeVerifier: self::base64Url(random_bytes(64)),
            issuedAt: time(),
        );
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
