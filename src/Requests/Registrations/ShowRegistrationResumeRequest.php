<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Registrations;

use Lacodix\MembergySdk\Support\RegistrationInput;
use Saloon\Enums\Method;
use SensitiveParameter;

final class ShowRegistrationResumeRequest extends AbstractRegistrationRequest
{
    protected Method $method = Method::GET;

    public function __construct(#[SensitiveParameter] private readonly string $token)
    {
        RegistrationInput::token($token, landing: true);
        parent::__construct();
    }

    public function resolveEndpoint(): string
    {
        return '/registrations/resume/'.rawurlencode($this->token);
    }
}
