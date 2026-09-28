<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class EventSearchHit implements SearchHit
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $title,
        public string $excerpt,
        public string $visibility,
        public bool $allDay,
        public ?string $startDate,
        public ?string $endDate,
        public ?DateTimeImmutable $startAt,
        public ?DateTimeImmutable $endAt,
        public string $timezone,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: Data::string($data, 'uuid'),
            title: Data::string($data, 'title'),
            excerpt: Data::string($data, 'excerpt'),
            visibility: Data::string($data, 'visibility'),
            allDay: Data::bool($data, 'all_day'),
            startDate: Data::nullableString($data, 'start_date'),
            endDate: Data::nullableString($data, 'end_date'),
            startAt: Data::nullableDate($data, 'start_at'),
            endAt: Data::nullableDate($data, 'end_at'),
            timezone: Data::string($data, 'timezone'),
            extra: Data::extra($data, [
                'uuid', 'title', 'excerpt', 'visibility', 'all_day', 'start_date',
                'end_date', 'start_at', 'end_at', 'timezone',
            ]),
        );
    }
}
