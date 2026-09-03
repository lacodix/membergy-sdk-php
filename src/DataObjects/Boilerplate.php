<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class Boilerplate
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $slug,
        public ?string $description,
        public string $content,
        public string $visibility,
        public DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: Data::string($data, 'slug'),
            description: Data::nullableString($data, 'description'),
            content: Data::string($data, 'content'),
            visibility: Data::string($data, 'visibility'),
            updatedAt: Data::date($data, 'updated_at'),
            extra: Data::extra($data, ['slug', 'description', 'content', 'visibility', 'updated_at']),
        );
    }
}
