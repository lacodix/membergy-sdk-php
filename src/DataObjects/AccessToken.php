<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class AccessToken
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public ?DateTimeImmutable $expiresAt,
        public bool $emailVerified,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: Data::string($data, 'access_token'),
            tokenType: Data::string($data, 'token_type'),
            expiresAt: Data::nullableDate($data, 'expires_at'),
            emailVerified: Data::bool($data, 'email_verified'),
            extra: Data::extra($data, [
                'access_token', 'token_type', 'expires_at', 'email_verified',
            ]),
        );
    }
}
