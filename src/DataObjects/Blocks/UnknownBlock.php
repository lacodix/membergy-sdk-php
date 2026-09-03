<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class UnknownBlock extends AbstractBlock
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        string $id,
        string $type,
        int $version,
        BlockSettings $settings,
        public array $data,
        public array $raw,
        array $extra = [],
    ) {
        parent::__construct($id, $type, $version, $settings, $extra);
    }
}
