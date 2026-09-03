<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class PersonProfile
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $firstname,
        public string $lastname,
        public ?string $salutation,
        public ?string $title,
        public ?string $gender,
        public string $email,
        public ?string $addressAddition,
        public ?string $addressStreet,
        public ?string $addressZip,
        public ?string $addressCity,
        public ?string $country,
        public ?string $dateOfBirth,
        public ?string $dateOfBirthSharingScope,
        public ?string $nameAtBirth,
        public ?string $dateOfWedding,
        public ?DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            firstname: Data::string($data, 'firstname'),
            lastname: Data::string($data, 'lastname'),
            salutation: Data::nullableString($data, 'salutation'),
            title: Data::nullableString($data, 'title'),
            gender: Data::nullableString($data, 'gender'),
            email: Data::string($data, 'email'),
            addressAddition: Data::nullableString($data, 'address_addition'),
            addressStreet: Data::nullableString($data, 'address_street'),
            addressZip: Data::nullableString($data, 'address_zip'),
            addressCity: Data::nullableString($data, 'address_city'),
            country: Data::nullableString($data, 'country'),
            dateOfBirth: Data::nullableString($data, 'date_of_birth'),
            dateOfBirthSharingScope: Data::nullableString($data, 'date_of_birth_sharing_scope'),
            nameAtBirth: Data::nullableString($data, 'name_at_birth'),
            dateOfWedding: Data::nullableString($data, 'date_of_wedding'),
            updatedAt: Data::nullableDate($data, 'updated_at'),
            extra: Data::extra($data, [
                'firstname', 'lastname', 'salutation', 'title', 'gender', 'email',
                'address_addition', 'address_street', 'address_zip', 'address_city',
                'country', 'date_of_birth', 'date_of_birth_sharing_scope',
                'name_at_birth', 'date_of_wedding', 'updated_at',
            ]),
        );
    }
}
