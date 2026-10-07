<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Registrations;

use Lacodix\MembergySdk\Support\RegistrationInput;
use Saloon\Enums\Method;
use SensitiveParameter;

final class RequestRegistrationMagicLinkRequest extends AbstractRegistrationJsonRequest
{
    protected Method $method = Method::POST;

    public function __construct(private readonly string $tenant, #[SensitiveParameter] private readonly string $email)
    {
        RegistrationInput::tenant($tenant);
        RegistrationInput::email($email);
        parent::__construct();
    }

    public function resolveEndpoint(): string
    {
        return '/tenant/'.rawurlencode($this->tenant).'/registrations/magic-link';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return ['email' => $this->email];
    }
}
