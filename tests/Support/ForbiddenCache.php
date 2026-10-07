<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Tests\Support;

use DateInterval;
use LogicException;
use Psr\SimpleCache\CacheInterface;

/** Registration must never touch a configured store, including reads and invalidations. */
final class ForbiddenCache implements CacheInterface
{
    public int $reads = 0;

    public int $writes = 0;

    public function get(string $key, mixed $default = null): mixed
    {
        $this->reads++;
        throw new LogicException('Unexpected registration cache access.');
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->writes++;
        throw new LogicException('Unexpected registration cache access.');
    }

    public function delete(string $key): bool
    {
        $this->writes++;
        throw new LogicException('Unexpected registration cache access.');
    }

    public function clear(): bool
    {
        $this->writes++;
        throw new LogicException('Unexpected registration cache access.');
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $this->reads++;
        throw new LogicException('Unexpected registration cache access.');
    }

    /** @param iterable<string, mixed> $values */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $this->writes++;
        throw new LogicException('Unexpected registration cache access.');
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $this->writes++;
        throw new LogicException('Unexpected registration cache access.');
    }

    public function has(string $key): bool
    {
        $this->reads++;
        throw new LogicException('Unexpected registration cache access.');
    }
}
