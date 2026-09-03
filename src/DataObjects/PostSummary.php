<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class PostSummary
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $title,
        public string $slug,
        public ?string $teaser,
        public ?PostCategoryReference $category,
        public ?MediaReference $featuredMedia,
        public string $visibility,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $category = Data::nullableObject($data, 'category');
        $featuredMedia = Data::nullableObject($data, 'featured_media');

        return new self(
            uuid: Data::string($data, 'uuid'),
            title: Data::string($data, 'title'),
            slug: Data::string($data, 'slug'),
            teaser: Data::nullableString($data, 'teaser'),
            category: $category === null ? null : PostCategoryReference::fromArray($category),
            featuredMedia: $featuredMedia === null ? null : MediaReference::fromArray($featuredMedia),
            visibility: Data::string($data, 'visibility'),
            publishedAt: Data::nullableDate($data, 'published_at'),
            updatedAt: Data::date($data, 'updated_at'),
            extra: Data::extra($data, [
                'uuid', 'title', 'slug', 'teaser', 'category', 'featured_media',
                'visibility', 'published_at', 'updated_at',
            ]),
        );
    }
}
