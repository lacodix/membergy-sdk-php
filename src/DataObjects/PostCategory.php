<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class PostCategory
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $title,
        public string $slug,
        public int $position,
        public ?string $description,
        public ?MediaReference $media,
        public DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $media = Data::nullableObject($data, 'media');

        return new self(
            uuid: Data::string($data, 'uuid'),
            title: Data::string($data, 'title'),
            slug: Data::string($data, 'slug'),
            position: Data::int($data, 'position'),
            description: Data::nullableString($data, 'description'),
            media: $media === null ? null : MediaReference::fromArray($media),
            updatedAt: Data::date($data, 'updated_at'),
            extra: Data::extra($data, [
                'uuid', 'title', 'slug', 'position', 'description', 'media', 'updated_at',
            ]),
        );
    }
}
