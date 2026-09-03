<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class FormField
{
    /**
     * @param  list<FormOption>  $options
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type,
        public bool $required,
        public bool $nullable,
        public bool $multiple,
        public mixed $default,
        public ?string $help,
        public array $options,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Data::string($data, 'name'),
            label: Data::string($data, 'label'),
            type: Data::string($data, 'type'),
            required: Data::bool($data, 'required'),
            nullable: Data::bool($data, 'nullable'),
            multiple: Data::bool($data, 'multiple'),
            default: Data::value($data, 'default'),
            help: Data::nullableString($data, 'help'),
            options: array_map(FormOption::fromArray(...), Data::objectList($data, 'options')),
            extra: Data::extra($data, [
                'name', 'label', 'type', 'required', 'nullable', 'multiple',
                'default', 'help', 'options',
            ]),
        );
    }
}
