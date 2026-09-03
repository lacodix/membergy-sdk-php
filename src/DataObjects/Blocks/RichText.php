<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class RichText
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $format,
        public string $value,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            format: Data::string($data, 'format'),
            value: Data::string($data, 'value'),
            extra: Data::extra($data, ['format', 'value']),
        );
    }
}
