<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use InvalidArgumentException;
use SensitiveParameter;

final readonly class ContactFormSubmission
{
    public function __construct(
        #[SensitiveParameter] public string $submissionTicket,
        #[SensitiveParameter] public ?string $email = null,
        #[SensitiveParameter] public ?string $subject = null,
        #[SensitiveParameter] public ?string $message = null,
        #[SensitiveParameter] public ?string $contactReason = null,
        #[SensitiveParameter] public ?string $website = null,
    ) {
        if ($this->submissionTicket === '' || ($this->website !== null && $this->website !== '')) {
            throw new InvalidArgumentException('Invalid contact form submission.');
        }

        foreach ($this->toArray() as $key => $value) {
            if (preg_match('//u', $value) !== 1) {
                throw new InvalidArgumentException('Invalid contact form submission.');
            }
            $limit = match ($key) {
                'email' => 254,
                'subject' => 200,
                'message' => 10000,
                default => null,
            };
            if ($limit !== null && preg_match_all('/./us', $value) > $limit) {
                throw new InvalidArgumentException('Invalid contact form submission.');
            }
            $controls = $key === 'message' ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\r(?!\n)/' : '/[\x00-\x1F\x7F]/';
            if (preg_match($controls, $value) === 1) {
                throw new InvalidArgumentException('Invalid contact form submission.');
            }
        }

        if ($this->email !== null && $this->email !== '' && filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Invalid contact form submission.');
        }
        // Match Saloon's default JSON encoding, including escaped Unicode/control bytes.
        if (strlen(json_encode($this->toArray(), JSON_THROW_ON_ERROR)) > 20000) {
            throw new InvalidArgumentException('Invalid contact form submission.');
        }
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['payload' => '[redacted]'];
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'submission_ticket' => $this->submissionTicket,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'contact_reason' => $this->contactReason,
            'website' => $this->website,
        ], static fn (?string $value): bool => $value !== null);
    }
}
