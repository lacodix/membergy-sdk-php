<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

final class RegistrationRateLimitException extends RegistrationException
{
    public function __construct(public readonly ?int $retryAfterSeconds)
    {
        parent::__construct('The registration rate limit was exceeded.', 429);
    }
}
