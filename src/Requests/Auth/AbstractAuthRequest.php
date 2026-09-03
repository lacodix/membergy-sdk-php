<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Auth;

use Saloon\Http\Request;

abstract class AbstractAuthRequest extends Request
{
    public const MEDIA_TYPE = 'application/vnd.membergy.auth-v1+json';

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Accept' => self::MEDIA_TYPE];
    }
}
