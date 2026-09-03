<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /api/tenant/{tenant}/content/posts
 *
 * Lists published posts. Respects CMS visibility:
 * without user token -> only public posts,
 * with user token    -> additionally posts visible to members.
 */
class ListPostsRequest extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, mixed>  $query  filter / sort / pagination params, e.g.
     *                                       ['filter[category]' => 'news', 'sort' => '-published_at', 'page' => 1, 'per_page' => 20]
     */
    public function __construct(
        protected string $tenant,
        protected array $query = [],
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/tenant/' . $this->tenant . '/content/posts';
    }

    public function defaultQuery(): array
    {
        return $this->query;
    }
}
