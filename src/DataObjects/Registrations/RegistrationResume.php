<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use DateTimeImmutable;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Exceptions\RegistrationIdentityException;
use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationResume
{
    private function __construct(
        public string $tenantSlug,
        public string $offerSlug,
        public int $offerVersionId,
        public RegistrationApplicant $applicant,
        public RegistrationPrefill $prefill,
        public DateTimeImmutable $expiresAt,
        private string $resumeContext,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        $context = Data::string($data, 'resume_context');
        if (preg_match('/^[a-f0-9]{64}$/D', $context) !== 1) {
            throw new HydrationException('Invalid registration resume response.');
        }

        return new self(
            Data::string(Data::object($data, 'tenant'), 'slug'),
            Data::string(Data::object($data, 'offer'), 'slug'),
            Data::int(Data::object($data, 'version'), 'id'),
            RegistrationApplicant::fromArray(Data::object($data, 'applicant')),
            RegistrationPrefill::fromArray(Data::object($data, 'prefill')),
            Data::date($data, 'resume_expires_at'), $context,
        );
    }

    public function assertBinding(string $tenant, string $offer, ?int $version = null): void
    {
        if ($tenant !== $this->tenantSlug || $offer !== $this->offerSlug || ($version !== null && $version !== $this->offerVersionId)) {
            throw new RegistrationIdentityException('Registration resume context does not match this request.');
        }
    }

    /** Supply only to one registration request, never a shared connector. */
    public function headerValue(): string
    {
        return $this->resumeContext;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['type' => self::class];
    }
}
