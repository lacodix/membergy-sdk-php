<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Registrations;

use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationResume;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationSubmission;
use Lacodix\MembergySdk\Support\RegistrationInput;
use Saloon\Enums\Method;
use SensitiveParameter;

final class SubmitRegistrationApplicationRequest extends AbstractRegistrationJsonRequest
{
    protected Method $method = Method::POST;

    public function __construct(private readonly string $tenant, private readonly string $slug, #[SensitiveParameter] private readonly RegistrationSubmission $submission, #[SensitiveParameter] ?RegistrationResume $resume = null)
    {
        RegistrationInput::tenant($tenant);
        RegistrationInput::slug($slug);
        $resume?->assertBinding($tenant, $slug, $submission->offerVersionId);
        parent::__construct($resume);
    }

    public function resolveEndpoint(): string
    {
        return '/tenant/'.rawurlencode($this->tenant).'/registration-offers/'.rawurlencode($this->slug).'/applications';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return $this->submission->toArray();
    }
}
