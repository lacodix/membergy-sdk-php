<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Exceptions;

final class CmsAuthenticationException extends MembergyException
{
    public function __construct()
    {
        parent::__construct('Configure a nonempty Membergy CMS credential before making API requests.');
    }
}
