<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationVersion
{
    public function __construct(
        public int $id,
        public string $label,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $effectiveAt,
        public ?DateTimeImmutable $registrationOpensAt,
        public ?DateTimeImmutable $registrationClosesAt,
        public string $sepaMode,
        public ?string $subtenantMode,
        public ?string $statusSelectionRule,
        public ?string $optionSelectionRule,
        public ?string $optionSelectionLabel,
        public bool $allowsMinorParticipants,
        public string $guardianParticipation,
        public bool $guardianParticipationPreselected,
        public ?RegistrationJoiningDate $joiningDate = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            id: Data::int($data, 'id'),
            label: Data::string($data, 'label'),
            validFrom: Data::date($data, 'valid_from'),
            effectiveAt: Data::date($data, 'effective_at'),
            registrationOpensAt: Data::nullableDate($data, 'registration_opens_at'),
            registrationClosesAt: Data::nullableDate($data, 'registration_closes_at'),
            sepaMode: Data::string($data, 'sepa_mode'),
            subtenantMode: Data::nullableString($data, 'subtenant_mode'),
            statusSelectionRule: Data::nullableString($data, 'status_selection_rule'),
            optionSelectionRule: Data::nullableString($data, 'option_selection_rule'),
            optionSelectionLabel: Data::nullableString($data, 'option_selection_label'),
            allowsMinorParticipants: Data::bool($data, 'allows_minor_participants'),
            guardianParticipation: Data::string($data, 'guardian_participation'),
            guardianParticipationPreselected: Data::bool($data, 'guardian_participation_preselected'),
            joiningDate: ($data['joining_date'] ?? null) === null
                ? null : RegistrationJoiningDate::fromArray(Data::object($data, 'joining_date')),
        );
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['type' => self::class];
    }
}
