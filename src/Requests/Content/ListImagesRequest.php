<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Saloon\Enums\Method;

final class ListImagesRequest extends AbstractListContentRequest
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return $this->contentEndpoint('images');
    }
}
