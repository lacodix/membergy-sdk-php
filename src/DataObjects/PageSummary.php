<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class PageSummary
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $title,
        public ?string $titleOnPage,
        public string $slug,
        public bool $isStart,
        public string $visibility,
        public ?MediaReference $heroMedia,
        public ?DateTimeImmutable $publishedAt,
        public DateTimeImmutable $updatedAt,
        public array $extra = [],
        public bool $hasMemberContinuation = false,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $heroMedia = Data::nullableObject($data, 'hero_media');

        return new self(
            uuid: Data::string($data, 'uuid'),
            title: Data::string($data, 'title'),
            titleOnPage: Data::nullableString($data, 'title_on_page'),
            slug: Data::string($data, 'slug'),
            isStart: Data::bool($data, 'is_start'),
            visibility: Data::string($data, 'visibility'),
            heroMedia: $heroMedia === null ? null : MediaReference::fromArray($heroMedia),
            publishedAt: Data::nullableDate($data, 'published_at'),
            updatedAt: Data::date($data, 'updated_at'),
            hasMemberContinuation: array_key_exists('has_member_continuation', $data) ? Data::bool($data, 'has_member_continuation') : false,
            extra: Data::extra($data, [
                'uuid', 'title', 'title_on_page', 'slug', 'is_start', 'visibility',
                'hero_media', 'published_at', 'updated_at',
            ]),
        );
    }
}
