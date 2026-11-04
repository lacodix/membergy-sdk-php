<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class ContactFormSchema
{
    /**
     * @param  list<ContactFormField>  $fields
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public int $schemaVersion,
        public array $fields,
        public ContactFormMessages $messages,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            schemaVersion: Data::int($data, 'schema_version'),
            fields: array_map(ContactFormField::fromArray(...), Data::objectList($data, 'fields')),
            messages: ContactFormMessages::fromArray(Data::object($data, 'messages')),
            extra: Data::extra($data, ['schema_version', 'fields', 'messages']),
        );
    }
}
