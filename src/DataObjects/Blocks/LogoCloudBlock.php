<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class LogoCloudBlock extends AbstractBlock
{
    /**
     * @param  list<LogoItem>  $items
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public ?string $title,
        public array $items,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'logo_cloud', $version, $settings, $extra);
    }
}
