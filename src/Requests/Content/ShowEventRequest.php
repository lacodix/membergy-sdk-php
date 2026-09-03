<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Saloon\Enums\Method;

final class ShowEventRequest extends AbstractContentRequest
{
    protected Method $method = Method::GET;

    public function __construct(string $tenant, private string $uuid)
    {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->contentEndpoint('events/'.rawurlencode($this->uuid));
    }
}
