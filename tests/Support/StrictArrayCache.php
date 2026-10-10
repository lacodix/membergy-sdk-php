<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Tests\Support;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/** Accepts only the key format every PSR-16 store must support. */
final class StrictArrayCache implements CacheInterface
{
    public function __construct(public readonly ArrayCache $inner = new ArrayCache) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validate($key);

        return $this->inner->get($key, $default);
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $this->validate($key);

        return $this->inner->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        $this->validate($key);

        return $this->inner->delete($key);
    }

    public function clear(): bool
    {
        return $this->inner->clear();
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /** @param iterable<string, mixed> $values */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $this->validate($key);

        return $this->inner->has($key);
    }

    private function validate(string $key): void
    {
        if (preg_match('/^[A-Za-z0-9_.]{1,64}$/D', $key) !== 1) {
            throw new class('Unsupported PSR-16 cache key.') extends \InvalidArgumentException implements InvalidArgumentException {};
        }
    }
}
