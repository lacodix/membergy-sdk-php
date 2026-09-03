<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\MenuTargets;

abstract readonly class InternalMenuTarget implements MenuTarget
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $slug,
        public string $title,
        public array $extra = [],
    ) {}
}
