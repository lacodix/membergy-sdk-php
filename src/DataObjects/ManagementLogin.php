<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class ManagementLogin
{
    public function __construct(#[SensitiveParameter] public string $url, public DateTimeImmutable $expiresAt) {}

    public function __debugInfo(): array
    {
        return ['url' => '[redacted]', 'expires_at' => $this->expiresAt];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(Data::string($data, 'url'), new DateTimeImmutable(Data::string($data, 'expires_at')));
    }
}
