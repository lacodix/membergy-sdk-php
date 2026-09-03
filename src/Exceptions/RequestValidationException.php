<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

use Throwable;

final class RequestValidationException extends MembergyException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public readonly array $errors,
        ?Throwable $previous = null,
    ) {
        parent::__construct('The Membergy API rejected the request parameters.', previous: $previous);
    }
}
