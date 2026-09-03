<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\DynamicIncludes;

use Lacodix\MembergySdk\DataObjects\PostSummary;

final readonly class PostCategoryInclude implements DynamicInclude
{
    /**
     * @param  list<PostSummary>  $posts
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $blockId,
        public array $posts,
        public array $extra = [],
    ) {}

    public function blockId(): string
    {
        return $this->blockId;
    }

    public function type(): string
    {
        return 'post_category';
    }
}
