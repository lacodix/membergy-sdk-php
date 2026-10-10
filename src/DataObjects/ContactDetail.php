<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class ContactDetail
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $key,
        public string $type,
        public string $value,
        public string $sharingScope,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(string $key, array $data): self
    {
        return new self(
            $key, Data::string($data, 'type'), Data::string($data, 'value'),
            Data::string($data, 'sharing_scope'), Data::extra($data, ['type', 'value', 'sharing_scope']),
        );
    }

    /** @return array{type: string, value: string, sharing_scope: string} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'value' => $this->value, 'sharing_scope' => $this->sharingScope];
    }
}
