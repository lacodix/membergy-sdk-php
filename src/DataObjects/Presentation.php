<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class Presentation
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public ?string $color,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            color: Data::nullableString($data, 'color'),
            extra: Data::extra($data, ['color']),
        );
    }
}
