<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

final class UnsafeRequestException extends MembergyException
{
    public function __construct()
    {
        parent::__construct('Membergy transport requires the configured trusted HTTP origin and immutable credentials, without URL credentials.');
    }
}
