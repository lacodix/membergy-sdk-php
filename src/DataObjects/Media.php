<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use DateTimeImmutable;
use Lacodix\MembergySdk\Support\Data;

/** @phpstan-consistent-constructor */
readonly class Media
{
    /**
     * @param  array{x: float, y: float}|null  $focus
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $uuid,
        public string $kind,
        public ?string $title,
        public ?string $alt,
        public ?string $description,
        public string $filename,
        public string $mimeType,
        public int $sizeBytes,
        public ?int $width,
        public ?int $height,
        public ?array $focus,
        public string $visibility,
        public string $url,
        public DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        $focusData = Data::nullableObject($data, 'focus');
        $focus = $focusData === null ? null : [
            'x' => Data::float($focusData, 'x'),
            'y' => Data::float($focusData, 'y'),
        ];

        return new static(
            uuid: Data::string($data, 'uuid'),
            kind: Data::string($data, 'kind'),
            title: Data::nullableString($data, 'title'),
            alt: Data::nullableString($data, 'alt'),
            description: Data::nullableString($data, 'description'),
            filename: Data::string($data, 'filename'),
            mimeType: Data::string($data, 'mime_type'),
            sizeBytes: Data::int($data, 'size_bytes'),
            width: Data::nullableInt($data, 'width'),
            height: Data::nullableInt($data, 'height'),
            focus: $focus,
            visibility: Data::string($data, 'visibility'),
            url: Data::string($data, 'url'),
            updatedAt: Data::date($data, 'updated_at'),
            extra: Data::extra($data, [
                'uuid', 'kind', 'title', 'alt', 'description', 'filename', 'mime_type',
                'size_bytes', 'width', 'height', 'focus', 'visibility', 'url', 'updated_at',
            ]),
        );
    }
}
