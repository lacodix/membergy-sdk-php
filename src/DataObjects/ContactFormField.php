<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class ContactFormField
{
    /**
     * @param  list<string>  $options
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $label,
        public bool $required,
        public bool $enabled,
        public array $options,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            key: Data::string($data, 'key'),
            type: Data::string($data, 'type'),
            label: Data::string($data, 'label'),
            required: Data::bool($data, 'required'),
            enabled: Data::bool($data, 'enabled'),
            options: Data::stringList($data, 'options'),
            extra: Data::extra($data, ['key', 'type', 'label', 'required', 'enabled', 'options']),
        );
    }
}
