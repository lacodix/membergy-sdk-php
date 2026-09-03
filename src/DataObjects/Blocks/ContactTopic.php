<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class ContactTopic
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $key,
        public string $label,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: Data::string($data, 'key'),
            label: Data::string($data, 'label'),
            extra: Data::extra($data, ['key', 'label']),
        );
    }
}
