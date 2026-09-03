<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class RawHtmlBlock extends AbstractBlock
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
        public string $html,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'raw_html', $version, $settings, $extra);
    }
}
