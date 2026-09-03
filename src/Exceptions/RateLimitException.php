<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

use Throwable;

final class RateLimitException extends MembergyException
{
    public function __construct(
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct('The Membergy API rate limit was exceeded.', previous: $previous);
    }
}
