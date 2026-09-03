<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\DataObjects\Blocks\Link;
use Lacodix\MembergySdk\Support\Data;

final readonly class HeroImage
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public MediaReference $media,
        public ?string $alt,
        public ?Link $link,
        public string $titlePosition,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $link = Data::nullableObject($data, 'link');

        return new self(
            media: MediaReference::fromArray(Data::object($data, 'media')),
            alt: Data::nullableString($data, 'alt'),
            link: $link === null ? null : Link::fromArray($link),
            titlePosition: Data::string($data, 'title_position'),
            extra: Data::extra($data, ['media', 'alt', 'link', 'title_position']),
        );
    }
}
