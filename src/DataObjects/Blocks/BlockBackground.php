<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class BlockBackground
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $type,
        public ?string $color,
        public ?BlockMediaReference $image,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $image = Data::nullableObject($data, 'image');

        return new self(
            type: Data::string($data, 'type'),
            color: Data::nullableString($data, 'color'),
            image: $image === null ? null : BlockMediaReference::fromArray($image),
            extra: Data::extra($data, ['type', 'color', 'image']),
        );
    }
}
