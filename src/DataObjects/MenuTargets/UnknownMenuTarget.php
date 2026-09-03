<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\MenuTargets;

final readonly class UnknownMenuTarget implements MenuTarget
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $targetType,
        public array $raw,
    ) {}

    public function type(): string
    {
        return $this->targetType;
    }
}
