<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class SearchMeta
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public int $page,
        public int $perPage,
        public bool $hasMore,
        public ?int $nextPage,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            page: Data::int($data, 'page'),
            perPage: Data::int($data, 'per_page'),
            hasMore: Data::bool($data, 'has_more'),
            nextPage: Data::nullableInt($data, 'next_page'),
            extra: Data::extra($data, ['page', 'per_page', 'has_more', 'next_page']),
        );
    }
}
