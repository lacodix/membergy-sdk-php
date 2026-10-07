<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Registrations;

use Lacodix\MembergySdk\Support\RegistrationInput;
use Saloon\Enums\Method;
use SensitiveParameter;

final class ShowRegistrationApplicationRequest extends AbstractRegistrationRequest
{
    protected Method $method = Method::GET;

    public function __construct(#[SensitiveParameter] private readonly string $publicId)
    {
        RegistrationInput::uuid($publicId);
        parent::__construct();
    }

    public function resolveEndpoint(): string
    {
        return '/registrations/'.$this->publicId;
    }
}
