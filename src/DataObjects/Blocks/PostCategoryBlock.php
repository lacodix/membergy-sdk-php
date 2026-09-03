<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

final readonly class PostCategoryBlock extends AbstractBlock
{
    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $dataExtra
     */
    public function __construct(
        string $id,
        int $version,
        BlockSettings $settings,
        public string $categoryUuid,
        public int $limit,
        public string $order,
        public bool $includeTeaser,
        public array $dataExtra = [],
        array $extra = [],
    ) {
        parent::__construct($id, 'post_category', $version, $settings, $extra);
    }
}
