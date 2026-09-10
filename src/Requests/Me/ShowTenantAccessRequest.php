<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Me;

use Saloon\Enums\Method;

final class ShowTenantAccessRequest extends AbstractSelfServiceRequest
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return $this->selfServiceEndpoint('access');
    }
}
