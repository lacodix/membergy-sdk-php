<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class PostSearchHit implements SearchHit
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $title,
        public string $slug,
        public string $excerpt,
        public string $visibility,
        public DateTimeImmutable $publishedAt,
        public array $extra = [],
        public bool $hasMemberContinuation = false,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: Data::string($data, 'uuid'),
            title: Data::string($data, 'title'),
            slug: Data::string($data, 'slug'),
            excerpt: Data::string($data, 'excerpt'),
            visibility: Data::string($data, 'visibility'),
            publishedAt: Data::date($data, 'published_at'),
            hasMemberContinuation: array_key_exists('has_member_continuation', $data) ? Data::bool($data, 'has_member_continuation') : false,
            extra: Data::extra($data, [
                'uuid', 'title', 'slug', 'excerpt', 'visibility', 'published_at',
            ]),
        );
    }
}
