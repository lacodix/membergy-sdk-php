<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Saloon\Enums\Method;
use Saloon\Http\Request;

/**
 * GET /api/tenant/{tenant}/content/posts/{slug}
 */
class ShowPostRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        protected string $tenant,
        protected string $slug,
    ) {
    }

    public function resolveEndpoint(): string
    {
        return '/tenant/' . $this->tenant . '/content/posts/' . rawurlencode($this->slug);
    }
}
