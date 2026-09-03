<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class MediaReference
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public string $kind,
        public ?string $title,
        public ?string $alt,
        public string $mimeType,
        public string $url,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: Data::string($data, 'uuid'),
            kind: Data::string($data, 'kind'),
            title: Data::nullableString($data, 'title'),
            alt: Data::nullableString($data, 'alt'),
            mimeType: Data::string($data, 'mime_type'),
            url: Data::string($data, 'url'),
            extra: Data::extra($data, ['uuid', 'kind', 'title', 'alt', 'mime_type', 'url']),
        );
    }
}
