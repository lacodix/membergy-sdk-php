<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationApplicant;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationField;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationOption;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationPrefill;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationRequirement;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationVersion;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationOffer
{
    /** @param list<RegistrationField> $formSchema
     * @param  list<RegistrationOption>  $options
     * @param  list<RegistrationRequirement>  $requirements
     */
    public function __construct(
        public string $slug,
        public string $title,
        public RegistrationVersion $version,
        public string $submissionTicket,
        public array $formSchema,
        public array $options,
        public array $requirements,
        public ?RegistrationApplicant $applicant,
        public ?RegistrationPrefill $prefill,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        $offer = Data::object($data, 'offer');
        $applicant = Data::nullableObject($data, 'applicant');
        $prefill = Data::nullableObject($data, 'prefill');
        if (($applicant === null) !== ($prefill === null)) {
            throw new HydrationException('Registration applicant and prefill must occur together.');
        }

        return new self(
            Data::string($offer, 'slug'), Data::string($offer, 'title'),
            RegistrationVersion::fromArray(Data::object($data, 'version')),
            Data::string($data, 'submission_ticket'),
            array_map(RegistrationField::fromArray(...), Data::objectList($data, 'form_schema')),
            array_map(RegistrationOption::fromArray(...), Data::objectList($data, 'options')),
            array_map(RegistrationRequirement::fromArray(...), Data::objectList($data, 'requirements')),
            $applicant === null ? null : RegistrationApplicant::fromArray($applicant),
            $prefill === null ? null : RegistrationPrefill::fromArray($prefill),
        );
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['type' => self::class];
    }
}
