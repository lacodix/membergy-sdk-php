<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class EventTag
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Data::int($data, 'id'),
            name: Data::string($data, 'name'),
        );
    }
}
