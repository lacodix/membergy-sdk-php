<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class Post
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
        public BlockDocument $content,
        public ?DynamicIncludes $included = null,
        public array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $included
     */
    public static function fromArray(array $data, ?array $included = null): self
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
            content: BlockDocument::fromArray(Data::object($data, 'content')),
            included: $included === null ? null : DynamicIncludes::fromArray($included),
            extra: Data::extra($data, [
                'uuid', 'title', 'slug', 'teaser', 'category', 'featured_media',
                'visibility', 'content', 'published_at', 'updated_at',
            ]),
        );
    }
}
