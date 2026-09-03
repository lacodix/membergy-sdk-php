<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

final readonly class Event
{
    /**
     * @param  list<EventTag>  $tags
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $uuid,
        public string $title,
        public ?string $description,
        public ?string $url,
        public bool $allDay,
        public ?string $startDate,
        public ?string $endDate,
        public ?DateTimeImmutable $startAt,
        public ?DateTimeImmutable $endAt,
        public string $timezone,
        public string $type,
        public string $visibility,
        public bool $isCancelled,
        public ?string $locationLabel,
        public ?float $locationLat,
        public ?float $locationLng,
        public array $tags,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: Data::string($data, 'uuid'),
            title: Data::string($data, 'title'),
            description: Data::nullableString($data, 'description'),
            url: Data::nullableString($data, 'url'),
            allDay: Data::bool($data, 'all_day'),
            startDate: Data::nullableString($data, 'start_date'),
            endDate: Data::nullableString($data, 'end_date'),
            startAt: Data::nullableDate($data, 'start_at'),
            endAt: Data::nullableDate($data, 'end_at'),
            timezone: Data::string($data, 'timezone'),
            type: Data::string($data, 'type'),
            visibility: Data::string($data, 'visibility'),
            isCancelled: Data::bool($data, 'is_cancelled'),
            locationLabel: Data::nullableString($data, 'location_label'),
            locationLat: Data::nullableFloat($data, 'location_lat'),
            locationLng: Data::nullableFloat($data, 'location_lng'),
            tags: array_map(EventTag::fromArray(...), Data::objectList($data, 'tags')),
            extra: Data::extra($data, [
                'uuid', 'title', 'description', 'url', 'all_day', 'start_date', 'end_date',
                'start_at', 'end_at', 'timezone', 'type', 'visibility', 'is_cancelled',
                'location_label', 'location_lat', 'location_lng', 'tags',
            ]),
        );
    }
}
