<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Saloon\Enums\Method;

final class ListNewsletterCategoriesRequest extends AbstractContentRequest
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return $this->contentEndpoint('newsletter/categories');
    }
}
