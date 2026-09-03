<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Support\Data;

final readonly class FormOption
{
    public function __construct(
        public int|string $value,
        public string $label,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $value = Data::value($data, 'value');
        if (! is_int($value) && ! is_string($value)) {
            throw new HydrationException("Invalid API payload: 'value' must be int|string.");
        }

        return new self(
            value: $value,
            label: Data::string($data, 'label'),
        );
    }
}
