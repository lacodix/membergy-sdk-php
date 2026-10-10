<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class AccessToken
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        #[SensitiveParameter] public string $accessToken,
        public string $tokenType,
        public ?DateTimeImmutable $expiresAt,
        public bool $emailVerified,
        public array $extra = [],
        public bool $remember = false,
        public ?string $loginEmail = null,
    ) {}

    public function __debugInfo(): array
    {
        return ['access_token' => '[redacted]', 'expires_at' => $this->expiresAt, 'email_verified' => $this->emailVerified];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: Data::string($data, 'access_token'),
            tokenType: Data::string($data, 'token_type'),
            expiresAt: Data::nullableDate($data, 'expires_at'),
            emailVerified: Data::bool($data, 'email_verified'),
            remember: array_key_exists('remember', $data) ? Data::bool($data, 'remember') : false,
            loginEmail: Data::nullableString($data, 'login_email'),
            extra: Data::extra($data, [
                'access_token', 'token_type', 'expires_at', 'email_verified', 'remember', 'login_email',
            ]),
        );
    }
}
