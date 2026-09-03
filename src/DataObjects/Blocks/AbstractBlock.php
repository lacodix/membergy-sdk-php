<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

abstract readonly class AbstractBlock implements Block
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $id,
        public string $blockType,
        public int $blockVersion,
        public BlockSettings $settings,
        public array $extra = [],
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->blockType;
    }

    public function version(): int
    {
        return $this->blockVersion;
    }
}
