<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Factories;

use Lacodix\MembergySdk\DataObjects\DynamicIncludes\DynamicInclude;
use Lacodix\MembergySdk\DataObjects\DynamicIncludes\PostCategoryInclude;
use Lacodix\MembergySdk\DataObjects\DynamicIncludes\UnknownDynamicInclude;
use Lacodix\MembergySdk\DataObjects\PostSummary;
use Lacodix\MembergySdk\Support\Data;

final class DynamicIncludeFactory
{
    /** @param array<string, mixed> $payload */
    public function fromArray(array $payload): DynamicInclude
    {
        $blockId = Data::string($payload, 'block_id');
        $type = Data::string($payload, 'type');
        $data = Data::object($payload, 'data');

        if ($type !== 'post_category') {
            return new UnknownDynamicInclude($blockId, $type, $data, $payload);
        }

        return new PostCategoryInclude(
            blockId: $blockId,
            posts: array_map(PostSummary::fromArray(...), Data::objectList($data, 'posts')),
            extra: Data::extra($payload, ['block_id', 'type', 'data']),
        );
    }
}
