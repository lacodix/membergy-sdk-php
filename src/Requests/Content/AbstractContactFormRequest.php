<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Content;

use Lacodix\MembergySdk\Requests\RedactsSensitivePayload;

abstract class AbstractContactFormRequest extends AbstractContentRequest
{
    use RedactsSensitivePayload;

    /** @return array<string, string> */
    protected function defaultHeaders(): array
    {
        return ['Cache-Control' => 'no-store'];
    }
}
