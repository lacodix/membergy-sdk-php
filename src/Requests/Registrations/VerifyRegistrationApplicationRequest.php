<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Registrations;

use Lacodix\MembergySdk\Support\RegistrationInput;
use Saloon\Enums\Method;
use SensitiveParameter;

final class VerifyRegistrationApplicationRequest extends AbstractRegistrationJsonRequest
{
    protected Method $method = Method::POST;

    public function __construct(#[SensitiveParameter] private readonly string $token)
    {
        RegistrationInput::token($token);
        parent::__construct();
    }

    public function resolveEndpoint(): string
    {
        return '/registrations/verification';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return ['token' => $this->token];
    }
}
