<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\MenuTargets;

final readonly class CustomLinkMenuTarget implements MenuTarget
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $url,
        public array $extra = [],
    ) {}

    public function type(): string
    {
        return 'custom_link';
    }
}
