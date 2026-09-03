<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Auth;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Traits\Body\HasJsonBody;

final class ResetPasswordRequest extends AbstractAuthRequest implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private string $token,
        private string $email,
        private string $password,
        private string $passwordConfirmation,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/auth/password/reset';
    }

    /** @return array<string, string> */
    public function defaultBody(): array
    {
        return [
            'token' => $this->token,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
        ];
    }
}
