<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class TextImageBlock extends AbstractBlock
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
        public RichText $body,
        public BlockMediaReference $image,
        public string $position,
        public string $width,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'text_image', $version, $settings, $extra);
    }
}
