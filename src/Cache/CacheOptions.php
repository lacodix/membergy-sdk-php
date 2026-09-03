<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Cache;

use InvalidArgumentException;

final readonly class CacheOptions
{
    /** @var array<string, int> */
    public array $resourceTtls;

    /**
     * @param  array<string, int>  $resourceTtls
     */
    public function __construct(
        public string $prefix = 'membergy-sdk',
        public int $defaultTtl = 300,
        public int $staleTtl = 86400,
        array $resourceTtls = [],
    ) {
        if ($this->prefix === '' || preg_match('/^[a-zA-Z0-9._:-]+$/', $this->prefix) !== 1) {
            throw new InvalidArgumentException('The Membergy cache prefix is invalid.');
        }

        if ($this->defaultTtl < 0 || $this->staleTtl < 0) {
            throw new InvalidArgumentException('Membergy cache TTL values cannot be negative.');
        }

        foreach ($resourceTtls as $resource => $ttl) {
            if (
                ! is_string($resource)
                || preg_match('/^[a-z0-9-]+$/', $resource) !== 1
                || ! is_int($ttl)
                || $ttl < 0
            ) {
                throw new InvalidArgumentException('Membergy resource cache TTLs must be non-negative integers.');
            }
        }

        $this->resourceTtls = $resourceTtls;
    }

    public function ttlFor(string $resource): int
    {
        return $this->resourceTtls[$resource] ?? $this->defaultTtl;
    }
}
