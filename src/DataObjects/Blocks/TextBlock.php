<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class TextBlock extends AbstractBlock
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
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'text', $version, $settings, $extra);
    }
}
