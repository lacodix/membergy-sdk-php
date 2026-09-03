<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Forms;

use Lacodix\MembergySdk\Requests\AbstractTenantRequest;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

final class SubmitRegisterFormRequest extends AbstractTenantRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    /** @param array<string, mixed> $values */
    public function __construct(string $tenant, private array $values)
    {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->tenantEndpoint('forms/register');
    }

    /** @return array<string, mixed> */
    public function defaultBody(): array
    {
        return $this->values;
    }
}
