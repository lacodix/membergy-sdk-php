<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class AnchorNavigationBlock extends AbstractBlock
{
    /**
     * @param  list<int>  $levels
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public array $levels,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'anchor_navigation', $version, $settings, $extra);
    }
}
