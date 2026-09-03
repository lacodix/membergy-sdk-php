<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class BlockColumn
{
    /**
     * @param  list<Block>  $blocks
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $width,
        public array $blocks,
        public array $extra = [],
    ) {}
}
