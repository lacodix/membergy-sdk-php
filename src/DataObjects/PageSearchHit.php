<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class PageSearchHit implements SearchHit
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $title,
        public string $slug,
        public string $excerpt,
        public string $visibility,
        public array $extra = [],
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
            extra: Data::extra($data, ['uuid', 'title', 'slug', 'excerpt', 'visibility']),
        );
    }
}
