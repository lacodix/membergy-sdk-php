<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

final class HttpRequestException extends MembergyException
{
    public function __construct(public readonly int $status)
    {
        parent::__construct('The Membergy API request failed.');
    }

    public function __debugInfo(): array
    {
        return ['message' => $this->getMessage(), 'status' => $this->status];
    }
}
