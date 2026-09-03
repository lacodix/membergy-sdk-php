<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class AuthResult
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $status,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            status: Data::string($data, 'status'),
            extra: Data::extra($data, ['status']),
        );
    }
}
