<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationPrefill
{
    /** @param array<string, mixed> $attributes
     * @param  list<RegistrationContact>  $contacts
     * @param  list<string>  $lockedFields
     */
    public function __construct(
        public array $attributes,
        public array $contacts,
        public array $lockedFields,
        public bool $addressIsFixed,
        public bool $isFollowUp,
        public ?string $desiredJoiningDate = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            Data::object($data, 'attributes'),
            array_map(RegistrationContact::fromArray(...), Data::objectList($data, 'contacts')),
            Data::stringList($data, 'locked_fields'),
            Data::bool($data, 'address_is_fixed'), Data::bool($data, 'is_follow_up'),
            Data::nullableString($data, 'desired_joining_date'),
        );
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['type' => self::class];
    }
}
