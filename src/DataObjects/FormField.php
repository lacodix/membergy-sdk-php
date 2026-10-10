<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class FormField
{
    /**
     * @param  list<FormField>  $fields
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
        public array $fields = [],
        public ?int $max = null,
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
            fields: array_key_exists('fields', $data) ? array_map(self::fromArray(...), Data::objectList($data, 'fields')) : [],
            max: Data::nullableInt($data, 'max'),
            extra: Data::extra($data, [
                'name', 'label', 'type', 'required', 'nullable', 'multiple',
                'default', 'help', 'options', 'fields', 'max',
            ]),
        );
    }
}
