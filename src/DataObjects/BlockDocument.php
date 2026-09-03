<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\DataObjects\Blocks\Block;
use Lacodix\MembergySdk\Factories\BlockFactory;
use Lacodix\MembergySdk\Support\Data;

final readonly class BlockDocument
{
    /**
     * @param  list<Block>  $blocks
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public int $schemaVersion,
        public array $blocks,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?BlockFactory $factory = null): self
    {
        $factory ??= new BlockFactory;

        return new self(
            schemaVersion: Data::int($data, 'schema_version'),
            blocks: array_map($factory->fromArray(...), Data::objectList($data, 'blocks')),
            extra: Data::extra($data, ['schema_version', 'blocks']),
        );
    }
}
