<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class Hero
{
    /**
     * @param  list<HeroImage>  $images
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public array $images,
        public ?HeroVideo $video,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $video = Data::nullableObject($data, 'video');

        return new self(
            images: array_map(HeroImage::fromArray(...), Data::objectList($data, 'images')),
            video: $video === null ? null : HeroVideo::fromArray($video),
            extra: Data::extra($data, ['images', 'video']),
        );
    }
}
