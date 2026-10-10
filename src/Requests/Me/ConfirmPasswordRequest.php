<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Me;

use Lacodix\MembergySdk\Requests\RedactsSensitivePayload;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;

final class ConfirmPasswordRequest extends AbstractSelfServiceRequest implements HasBody
{
    use HasJsonBody;
    use RedactsSensitivePayload;

    protected Method $method = Method::POST;

    public function __construct(string $tenant, #[SensitiveParameter] private string $password)
    {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->selfServiceEndpoint('password/confirm');
    }

    /** @return array<string, string> */
    public function defaultBody(): array
    {
        return ['password' => $this->password];
    }
}
