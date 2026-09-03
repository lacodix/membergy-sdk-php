<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Tests\Support;

use Lacodix\MembergySdk\Cache\CacheClock;

final class MutableCacheClock implements CacheClock
{
    public function __construct(public int $timestamp = 1_000_000) {}

    public function now(): int
    {
        return $this->timestamp;
    }

    public function advance(int $seconds): void
    {
        $this->timestamp += $seconds;
    }
}
