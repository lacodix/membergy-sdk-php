<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Auth;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

final class CreateTokenRequest extends AbstractAuthRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private string $email,
        private string $password,
        private string $deviceName,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/token';
    }

    /** @return array<string, string> */
    public function defaultBody(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
            'device_name' => $this->deviceName,
        ];
    }
}
