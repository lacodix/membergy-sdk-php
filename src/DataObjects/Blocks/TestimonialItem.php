<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

use Lacodix\MembergySdk\Support\Data;

final readonly class TestimonialItem
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public RichText $text,
        public string $name,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            text: RichText::fromArray(Data::object($data, 'text')),
            name: Data::string($data, 'name'),
            extra: Data::extra($data, ['text', 'name']),
        );
    }
}
