<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Saloon\Enums\Method;

final class ShowPostRequest extends AbstractContentRequest
{
    protected Method $method = Method::GET;

    /** @param array<string, string> $queryParameters */
    public function __construct(string $tenant, private string $slug, private array $queryParameters = [])
    {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->contentEndpoint('posts/'.rawurlencode($this->slug));
    }

    /** @return array<string, string> */
    public function defaultQuery(): array
    {
        return $this->queryParameters;
    }
}
