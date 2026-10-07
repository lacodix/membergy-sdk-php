<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

final class RegistrationValidationException extends RegistrationException
{
    /** @param list<string> $fields Allowlisted request paths with bounded indexes; no server messages or input values. */
    public function __construct(public readonly array $fields)
    {
        parent::__construct('The registration input was rejected.', 422);
    }
}
