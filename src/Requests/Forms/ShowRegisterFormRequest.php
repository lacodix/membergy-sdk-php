<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Forms;

use Lacodix\MembergySdk\Requests\AbstractTenantRequest;
use Saloon\Enums\Method;

final class ShowRegisterFormRequest extends AbstractTenantRequest
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return $this->tenantEndpoint('forms/register');
    }
}
