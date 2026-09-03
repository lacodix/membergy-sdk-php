<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use InvalidArgumentException;

abstract class AbstractPaginatedResource extends AbstractResource
{
    public function page(int $page): static
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be at least 1.');
        }

        return $this->withQuery(['page' => $page]);
    }

    public function perPage(int $perPage): static
    {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Per-page must be between 1 and 100.');
        }

        return $this->withQuery(['per_page' => $perPage]);
    }
}
