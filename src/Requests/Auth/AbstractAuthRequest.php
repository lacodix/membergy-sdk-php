<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Auth;

use Lacodix\MembergySdk\Requests\RedactsSensitivePayload;
use Saloon\Http\Request;

abstract class AbstractAuthRequest extends Request
{
    use RedactsSensitivePayload;

    public const MEDIA_TYPE = 'application/vnd.membergy.auth-v1+json';

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Accept' => self::MEDIA_TYPE];
    }
}
