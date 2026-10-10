<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Me;

use Lacodix\MembergySdk\Requests\RedactsSensitivePayload;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;

final class UpdatePasswordRequest extends AbstractSelfServiceRequest implements HasBody
{
    use HasJsonBody;
    use RedactsSensitivePayload;

    protected Method $method = Method::PUT;

    public function __construct(
        string $tenant,
        #[SensitiveParameter] private string $currentPassword,
        #[SensitiveParameter] private string $password,
        #[SensitiveParameter] private string $passwordConfirmation,
    ) {
        parent::__construct($tenant);
    }

    public function resolveEndpoint(): string
    {
        return $this->selfServiceEndpoint('password');
    }

    /** @return array<string, string> */
    public function defaultBody(): array
    {
        return [
            'current_password' => $this->currentPassword,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
        ];
    }
}
