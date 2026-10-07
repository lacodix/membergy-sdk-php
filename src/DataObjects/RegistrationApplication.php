<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationApplication
{
    public function __construct(
        public string $publicId,
        public string $status,
        public ?DateTimeImmutable $submittedAt = null,
        public ?int $participantCount = null,
        public ?string $offerSlug = null,
        public ?string $offerTitle = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        $offer = Data::nullableObject($data, 'offer');

        return new self(
            Data::string($data, 'public_id'), Data::string($data, 'status'),
            Data::nullableDate($data, 'submitted_at'), Data::nullableInt($data, 'participant_count'),
            $offer === null ? null : Data::string($offer, 'slug'),
            $offer === null ? null : Data::string($offer, 'title'),
        );
    }
}
