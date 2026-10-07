<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Registrations;

use Lacodix\MembergySdk\Support\RegistrationInput;
use Saloon\Enums\Method;
use SensitiveParameter;

final class ResendRegistrationVerificationRequest extends AbstractRegistrationJsonRequest
{
    protected Method $method = Method::POST;

    public function __construct(#[SensitiveParameter] private readonly string $publicId, #[SensitiveParameter] private readonly string $submissionKey, #[SensitiveParameter] private readonly string $email)
    {
        RegistrationInput::uuid($publicId);
        RegistrationInput::uuid($submissionKey);
        RegistrationInput::email($email);
        parent::__construct();
    }

    public function resolveEndpoint(): string
    {
        return '/registrations/verification/resend';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return ['public_id' => $this->publicId, 'submission_key' => $this->submissionKey, 'email' => $this->email];
    }
}
