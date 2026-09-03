<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\DataObjects\Post;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Requests\Content\ListPostsRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPostRequest;
use Saloon\Exceptions\Request\Statuses\NotFoundException;

/**
 * Fluent access to the /content/posts endpoints.
 *
 * Usage:
 *   $client->content()->posts()
 *       ->category('news')
 *       ->sort('-published_at')
 *       ->perPage(20)
 *       ->get();
 *
 *   $client->content()->posts()->find('my-slug');
 *
 * Chained modifier methods return a cloned resource — the builder
 * is immutable, which keeps state predictable and makes re-using a
 * base query safe (e.g. in a Livewire component).
 */
class PostsResource
{
    /**
     * @param  array<string, mixed>  $query
     */
    public function __construct(
        private readonly MembergyConnector $connector,
        private readonly array $query = [],
    ) {
    }

    public function category(string $category): self
    {
        return $this->with(['category' => $category]);
    }

    public function visibility(string $visibility): self
    {
        return $this->with(['visibility' => $visibility]);
    }

    public function sort(string $sort): self
    {
        return $this->with(['sort' => $sort]);
    }

    public function page(int $page): self
    {
        return $this->with(['page' => $page]);
    }

    public function perPage(int $perPage): self
    {
        return $this->with(['per_page' => $perPage]);
    }

    /**
     * Add arbitrary query parameters. Useful for filters not yet
     * covered by a named convenience method.
     *
     * @param  array<string, mixed>  $params
     */
    public function where(array $params): self
    {
        return $this->with($params);
    }

    /**
     * Execute the configured query and return a paginated result of Post DTOs.
     *
     * @return PaginatedResult<Post>
     */
    public function get(): PaginatedResult
    {
        $response = $this->connector->send(
            new ListPostsRequest($this->connector->tenant(), $this->query)
        );

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        $items = array_map(
            static fn (array $item): Post => Post::fromArray($item),
            $payload['data'] ?? [],
        );

        return new PaginatedResult(
            items: $items,
            meta: $payload['meta'] ?? [],
            links: $payload['links'] ?? [],
        );
    }

    public function find(string $slug): Post
    {
        try {
            $response = $this->connector->send(
                new ShowPostRequest($this->connector->tenant(), $slug)
            );
        } catch (NotFoundException $e) {
            throw new ResourceNotFoundException(
                "Post with slug '{$slug}' not found.",
                previous: $e,
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        // Resourcerer may wrap single resources in a "data" key depending
        // on the resource's $wrap property; handle both shapes.
        $data = array_key_exists('data', $payload) && is_array($payload['data'])
            ? $payload['data']
            : $payload;

        return Post::fromArray($data);
    }

    /**
     * @param  array<string, mixed>  $extraParams
     */
    private function with(array $extraParams): self
    {
        return new self($this->connector, [...$this->query, ...$extraParams]);
    }
}
