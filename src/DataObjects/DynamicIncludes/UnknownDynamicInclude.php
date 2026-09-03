<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\DynamicIncludes;

final readonly class UnknownDynamicInclude implements DynamicInclude
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $blockId,
        public string $includeType,
        public array $data,
        public array $raw,
    ) {}

    public function blockId(): string
    {
        return $this->blockId;
    }

    public function type(): string
    {
        return $this->includeType;
    }
}
