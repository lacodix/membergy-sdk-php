<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationApplicant
{
    public function __construct(
        public string $email,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            email: Data::string($data, 'email'),
        );
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['type' => self::class];
    }
}
