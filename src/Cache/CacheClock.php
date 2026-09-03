<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Cache;

interface CacheClock
{
    public function now(): int;
}
