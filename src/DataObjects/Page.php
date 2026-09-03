<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class Page
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $title,
        public ?string $titleOnPage,
        public string $slug,
        public bool $isStart,
        public string $visibility,
        public Hero $hero,
        public Seo $seo,
        public Presentation $presentation,
        public BlockDocument $content,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $updatedAt,
        public ?DynamicIncludes $included = null,
        public array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $included
     */
    public static function fromArray(array $data, ?array $included = null): self
    {
        return new self(
            uuid: Data::string($data, 'uuid'),
            title: Data::string($data, 'title'),
            titleOnPage: Data::nullableString($data, 'title_on_page'),
            slug: Data::string($data, 'slug'),
            isStart: Data::bool($data, 'is_start'),
            visibility: Data::string($data, 'visibility'),
            hero: Hero::fromArray(Data::object($data, 'hero')),
            seo: Seo::fromArray(Data::object($data, 'seo')),
            presentation: Presentation::fromArray(Data::object($data, 'presentation')),
            content: BlockDocument::fromArray(Data::object($data, 'content')),
            publishedAt: Data::nullableDate($data, 'published_at'),
            updatedAt: Data::date($data, 'updated_at'),
            included: $included === null ? null : DynamicIncludes::fromArray($included),
            extra: Data::extra($data, [
                'uuid', 'title', 'title_on_page', 'slug', 'is_start', 'visibility',
                'hero', 'seo', 'presentation', 'content', 'published_at', 'updated_at',
            ]),
        );
    }
}
