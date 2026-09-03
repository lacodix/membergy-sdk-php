<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\DataObjects\PostCategory;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Requests\Content\ListPostCategoriesRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPostCategoryRequest;
use Lacodix\MembergySdk\Support\Payload;

final class PostCategoriesResource extends AbstractPaginatedResource
{
    /** @return PaginatedResult<PostCategory> */
    public function get(): PaginatedResult
    {
        return $this->paginate(
            new ListPostCategoriesRequest($this->connector->tenant(), $this->query),
            PostCategory::fromArray(...),
        );
    }

    public function find(string $slug): PostCategory
    {
        try {
            $response = $this->send(
                new ShowPostCategoryRequest($this->connector->tenant(), $slug),
            );
        } catch (ResourceNotFoundException $exception) {
            throw new ResourceNotFoundException(
                "Post category with slug '{$slug}' not found.",
                previous: $exception,
            );
        }

        return PostCategory::fromArray(Payload::data(Payload::fromResponse($response)));
    }
}
