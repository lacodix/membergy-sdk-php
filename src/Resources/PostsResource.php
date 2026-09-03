<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\DataObjects\Post;
use Lacodix\MembergySdk\DataObjects\PostSummary;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Requests\Content\ListPostsRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPostRequest;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Support\Payload;

final class PostsResource extends AbstractPaginatedResource
{
    public function category(string $slug): self
    {
        return $this->withQuery(['category' => $slug]);
    }

    /** @return PaginatedResult<PostSummary> */
    public function get(): PaginatedResult
    {
        return $this->paginate(
            new ListPostsRequest($this->connector->tenant(), $this->query),
            PostSummary::fromArray(...),
        );
    }

    public function find(string $slug, bool $includeDynamic = false): Post
    {
        try {
            $response = $this->send(new ShowPostRequest(
                $this->connector->tenant(),
                $slug,
                $includeDynamic ? ['include' => 'dynamic'] : [],
            ));
        } catch (ResourceNotFoundException $exception) {
            throw new ResourceNotFoundException("Post with slug '{$slug}' not found.", previous: $exception);
        }

        $payload = Payload::fromResponse($response);

        return Post::fromArray(
            Payload::data($payload),
            Data::nullableObject($payload, 'included'),
        );
    }
}
