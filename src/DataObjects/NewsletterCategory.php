<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class NewsletterCategory
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $name,
        public string $visibility,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: Data::string($data, 'uuid'),
            name: Data::string($data, 'name'),
            visibility: Data::string($data, 'visibility'),
            extra: Data::extra($data, ['uuid', 'name', 'visibility']),
        );
    }
}
