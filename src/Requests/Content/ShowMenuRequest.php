<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Saloon\Enums\Method;

final class ShowMenuRequest extends AbstractContentRequest
{
    protected Method $method = Method::GET;

    public function __construct(string $tenant, private string $handle)
    {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->contentEndpoint('menus/'.rawurlencode($this->handle));
    }
}
