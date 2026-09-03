<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class MenuSummary
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $title,
        public string $handle,
        public string $visibility,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: Data::string($data, 'uuid'),
            title: Data::string($data, 'title'),
            handle: Data::string($data, 'handle'),
            visibility: Data::string($data, 'visibility'),
            publishedAt: Data::nullableDate($data, 'published_at'),
            updatedAt: Data::date($data, 'updated_at'),
            extra: Data::extra($data, [
                'uuid', 'title', 'handle', 'visibility', 'published_at', 'updated_at',
            ]),
        );
    }
}
