<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Auth;

use Saloon\Enums\Method;

final class RevokeCurrentTokenRequest extends AbstractAuthRequest
{
    protected Method $method = Method::DELETE;

    public function resolveEndpoint(): string
    {
        return '/token';
    }
}
