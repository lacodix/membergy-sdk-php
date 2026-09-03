<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class FormDefinition
{
    /**
     * @param  list<FormField>  $fields
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $name,
        public ?string $title,
        public array $fields,
        public array $values,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Data::string($data, 'name'),
            title: Data::nullableString($data, 'title'),
            fields: array_map(FormField::fromArray(...), Data::objectList($data, 'fields')),
            values: Data::object($data, 'values'),
            extra: Data::extra($data, ['name', 'title', 'fields', 'values']),
        );
    }
}
