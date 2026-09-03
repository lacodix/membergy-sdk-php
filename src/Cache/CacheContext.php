<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Cache;

use Psr\SimpleCache\CacheInterface;

final readonly class CacheContext
{
    public function __construct(
        public CacheInterface $store,
        public string $key,
        public string $resource,
        public string $scope,
        public ?CacheEntry $entry,
    ) {}
}
