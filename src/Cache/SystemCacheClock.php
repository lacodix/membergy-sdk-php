<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Cache;

final class SystemCacheClock implements CacheClock
{
    public function now(): int
    {
        return time();
    }
}
