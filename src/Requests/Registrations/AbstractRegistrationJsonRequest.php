<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Registrations;

use Saloon\Contracts\Body\HasBody;
use Saloon\Traits\Body\HasJsonBody;

abstract class AbstractRegistrationJsonRequest extends AbstractRegistrationRequest implements HasBody
{
    use HasJsonBody;
}
