<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\MenuTargets;

final readonly class PostCategoryMenuTarget extends InternalMenuTarget
{
    public function type(): string
    {
        return 'post_category';
    }
}
