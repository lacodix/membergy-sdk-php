<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class VideoBlock extends AbstractBlock
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
        public UuidReference $video,
        public ?BlockMediaReference $poster,
        public ?string $caption,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'video', $version, $settings, $extra);
    }
}
