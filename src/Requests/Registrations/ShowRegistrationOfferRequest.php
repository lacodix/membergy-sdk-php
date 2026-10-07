<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Registrations;

use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationResume;
use Lacodix\MembergySdk\Support\RegistrationInput;
use Saloon\Enums\Method;
use SensitiveParameter;

final class ShowRegistrationOfferRequest extends AbstractRegistrationRequest
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $tenant, private readonly string $slug, #[SensitiveParameter] ?RegistrationResume $resume = null)
    {
        RegistrationInput::tenant($tenant);
        RegistrationInput::slug($slug);
        $resume?->assertBinding($tenant, $slug);
        parent::__construct($resume);
    }

    public function resolveEndpoint(): string
    {
        return '/tenant/'.rawurlencode($this->tenant).'/registration-offers/'.rawurlencode($this->slug);
    }
}
