<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class ColumnsBlock extends AbstractBlock
{
    /**
     * @param  list<BlockColumn>  $columns
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public ?string $title,
        public array $columns,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'columns', $version, $settings, $extra);
    }
}
