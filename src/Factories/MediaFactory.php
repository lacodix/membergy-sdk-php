<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Factories;

use Lacodix\MembergySdk\DataObjects\FileReference;
use Lacodix\MembergySdk\DataObjects\Image;
use Lacodix\MembergySdk\DataObjects\Media;
use Lacodix\MembergySdk\Support\Data;

final class MediaFactory
{
    /** @param array<string, mixed> $data */
    public function fromArray(array $data): Media
    {
        return match (Data::string($data, 'kind')) {
            'image' => Image::fromArray($data),
            'file', 'video' => FileReference::fromArray($data),
            default => Media::fromArray($data),
        };
    }

    /** @param array<string, mixed> $data */
    public function imageFromArray(array $data): Image
    {
        return Image::fromArray($data);
    }

    /** @param array<string, mixed> $data */
    public function fileFromArray(array $data): FileReference
    {
        return FileReference::fromArray($data);
    }
}
