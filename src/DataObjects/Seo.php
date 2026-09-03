<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class Seo
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?MediaReference $image,
        public ?string $canonicalUrl,
        public string $robots,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $image = Data::nullableObject($data, 'image');

        return new self(
            title: Data::nullableString($data, 'title'),
            description: Data::nullableString($data, 'description'),
            image: $image === null ? null : MediaReference::fromArray($image),
            canonicalUrl: Data::nullableString($data, 'canonical_url'),
            robots: Data::string($data, 'robots'),
            extra: Data::extra($data, ['title', 'description', 'image', 'canonical_url', 'robots']),
        );
    }
}
