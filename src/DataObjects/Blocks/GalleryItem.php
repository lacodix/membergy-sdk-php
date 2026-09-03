<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class GalleryItem
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public BlockMediaReference $image,
        public ?string $caption,
        public ?string $overlayText,
        public ?Link $link,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $link = Data::nullableObject($data, 'link');

        return new self(
            image: BlockMediaReference::fromArray(Data::object($data, 'image')),
            caption: Data::nullableString($data, 'caption'),
            overlayText: Data::nullableString($data, 'overlay_text'),
            link: $link === null ? null : Link::fromArray($link),
            extra: Data::extra($data, ['image', 'caption', 'overlay_text', 'link']),
        );
    }
}
