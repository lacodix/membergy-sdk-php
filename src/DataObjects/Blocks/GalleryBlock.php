<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class GalleryBlock extends AbstractBlock
{
    /**
     * @param  list<GalleryItem>  $items
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public ?string $title,
        public array $items,
        public int $perPage,
        public bool $pagination,
        public bool $autoplay,
        public bool $lightbox,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'gallery', $version, $settings, $extra);
    }
}
