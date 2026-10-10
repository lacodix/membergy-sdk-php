<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Auth;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;
use SensitiveParameter;

final class CreateTokenRequest extends AbstractAuthRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private string $email,
        #[SensitiveParameter] private string $password,
        private string $deviceName,
        private bool $remember = false,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/token';
    }

    /** @return array<string, string|bool> */
    public function defaultBody(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
            'device_name' => $this->deviceName,
            'remember' => $this->remember,
        ];
    }
}
