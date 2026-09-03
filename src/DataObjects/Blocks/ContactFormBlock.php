<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class ContactFormBlock extends AbstractBlock
{
    /**
     * @param  list<ContactTopic>  $topics
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public string $formHandle,
        public array $topics,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'contact_form', $version, $settings, $extra);
    }
}
