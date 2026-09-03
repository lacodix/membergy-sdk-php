<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class AgendaBlock extends AbstractBlock
{
    /**
     * @param  list<AgendaItem>  $items
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public ?string $title,
        public ?RichText $intro,
        public array $items,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'agenda', $version, $settings, $extra);
    }
}
