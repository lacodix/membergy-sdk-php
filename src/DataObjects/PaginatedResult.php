<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

/**
 * Generic paginated result.
 *
 * @template T
 */
final class PaginatedResult
{
    /**
     * @param  list<T>  $items
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $links
     */
    public function __construct(
        public readonly array $items,
        public readonly array $meta,
        public readonly array $links,
    ) {
    }

    public function currentPage(): ?int
    {
        return isset($this->meta['current_page']) ? (int) $this->meta['current_page'] : null;
    }

    public function lastPage(): ?int
    {
        return isset($this->meta['last_page']) ? (int) $this->meta['last_page'] : null;
    }

    public function total(): ?int
    {
        return isset($this->meta['total']) ? (int) $this->meta['total'] : null;
    }

    public function perPage(): ?int
    {
        return isset($this->meta['per_page']) ? (int) $this->meta['per_page'] : null;
    }

    public function hasMorePages(): bool
    {
        $current = $this->currentPage();
        $last = $this->lastPage();

        return $current !== null && $last !== null && $current < $last;
    }
}
