<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class BlockMediaReference
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $uuid,
        public ?string $alt,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            uuid: Data::string($data, 'uuid'),
            alt: Data::nullableString($data, 'alt'),
            extra: Data::extra($data, ['uuid', 'alt']),
        );
    }
}
