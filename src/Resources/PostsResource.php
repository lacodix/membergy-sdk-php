<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use InvalidArgumentException;
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
    public function cursor(?string $cursor = null): self
    {
        if ($cursor !== null && trim($cursor) === '') {
            throw new InvalidArgumentException('Cursor must not be empty.');
        }

        $query = $this->query;
        unset($query['page'], $query['cursor']);
        $query['pagination'] = 'cursor';

        if ($cursor !== null) {
            $query['cursor'] = $cursor;
        }

        return new self($this->connector, $query);
    }

    public function page(int $page): static
    {
        $resource = parent::page($page);
        $query = $resource->query;
        unset($query['pagination'], $query['cursor']);

        return new self($this->connector, $query);
    }

    public function category(string $slug): self
    {
        if (array_key_exists('categories', $this->query)) {
            throw new InvalidArgumentException('Category and categories cannot be combined.');
        }

        return $this->withQuery(['category' => $slug]);
    }

    public function categories(string ...$slugs): static
    {
        if (array_key_exists('category', $this->query)) {
            throw new InvalidArgumentException('Category and categories cannot be combined.');
        }
        $this->assertCategorySlugs($slugs);

        return $this->withQuery(['categories' => $slugs]);
    }

    public function excludeCategories(string ...$slugs): static
    {
        $this->assertCategorySlugs($slugs);

        return $this->withQuery(['exclude_categories' => $slugs]);
    }

    /** @param array<array-key, string> $slugs */
    private function assertCategorySlugs(array $slugs): void
    {
        if (! array_is_list($slugs) || count($slugs) < 1 || count($slugs) > 100) {
            throw new InvalidArgumentException('Category filters require a list of 1 to 100 slugs.');
        }
        foreach ($slugs as $slug) {
            $length = preg_match_all('/./us', $slug);
            if (trim($slug) === '' || $length === false || $length > 255) {
                throw new InvalidArgumentException('Category slugs must contain 1 to 255 UTF-8 characters.');
            }
        }
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
        } catch (ResourceNotFoundException) {
            throw new ResourceNotFoundException("Post with slug '{$slug}' not found.");
        }

        $payload = Payload::fromResponse($response);

        return Post::fromArray(
            Payload::data($payload),
            Data::nullableObject($payload, 'included'),
        );
    }
}
