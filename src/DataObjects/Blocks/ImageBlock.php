<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class ImageBlock extends AbstractBlock
{
    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public ?string $title,
        public BlockMediaReference $image,
        public ?string $caption,
        public ?Link $link,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'image', $version, $settings, $extra);
    }
}
