<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

final class TransportException extends MembergyException
{
    public function __construct()
    {
        parent::__construct('The Membergy API transport failed.');
    }

    public function __debugInfo(): array
    {
        return ['message' => $this->getMessage()];
    }
}
