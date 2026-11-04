<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class ContactFormDefinition
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public ContactFormSchema $definition,
        #[SensitiveParameter] public string $submissionTicket,
        public int $minimumSeconds,
        public DateTimeImmutable $expiresAt,
        public array $extra = [],
    ) {}

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['uuid' => $this->uuid, 'submission_ticket' => '[redacted]'];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            uuid: Data::string($data, 'uuid'),
            name: Data::string($data, 'name'),
            definition: ContactFormSchema::fromArray(Data::object($data, 'definition')),
            submissionTicket: Data::string($data, 'submission_ticket'),
            minimumSeconds: Data::int($data, 'minimum_seconds'),
            expiresAt: Data::date($data, 'expires_at'),
            extra: Data::extra($data, ['uuid', 'name', 'definition', 'submission_ticket', 'minimum_seconds', 'expires_at']),
        );
    }
}
