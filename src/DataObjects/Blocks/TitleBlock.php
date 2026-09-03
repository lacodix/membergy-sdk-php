<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class TitleBlock extends AbstractBlock
{
    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public string $text,
        public int $level,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'title', $version, $settings, $extra);
    }
}
