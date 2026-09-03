<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Media;

use InvalidArgumentException;
use Lacodix\MembergySdk\DataObjects\Image;
use Lacodix\MembergySdk\DataObjects\MediaReference;

final readonly class MediaUrlFactory
{
    public function __construct(
        private string $apiBaseUrl,
        private string $tenant,
    ) {}

    public function image(string $uuid): ImageUrlBuilder
    {
        return new ImageUrlBuilder($this->endpoint('image', $uuid));
    }

    public function fromImage(Image|MediaReference $image): ImageUrlBuilder
    {
        return new ImageUrlBuilder($image->url);
    }

    public function file(string $uuid): string
    {
        return $this->endpoint('file', $uuid);
    }

    private function endpoint(string $kind, string $uuid): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) !== 1) {
            throw new InvalidArgumentException("Invalid Membergy media UUID '{$uuid}'.");
        }

        return rtrim($this->apiBaseUrl, '/')
            .'/tenant/'.rawurlencode($this->tenant)
            .'/content/'.$kind.'/'.rawurlencode($uuid);
    }
}
