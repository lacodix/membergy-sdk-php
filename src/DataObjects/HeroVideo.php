<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class HeroVideo
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public MediaReference $media,
        public ?MediaReference $poster,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $poster = Data::nullableObject($data, 'poster');

        return new self(
            media: MediaReference::fromArray(Data::object($data, 'media')),
            poster: $poster === null ? null : MediaReference::fromArray($poster),
            extra: Data::extra($data, ['media', 'poster']),
        );
    }
}
