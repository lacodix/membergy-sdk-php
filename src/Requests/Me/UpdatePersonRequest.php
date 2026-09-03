<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Me;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

final class UpdatePersonRequest extends AbstractSelfServiceRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    /** @param array<string, mixed> $values */
    public function __construct(string $tenant, private array $values)
    {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->selfServiceEndpoint('person');
    }

    /** @return array<string, mixed> */
    public function defaultBody(): array
    {
        return $this->values;
    }
}
