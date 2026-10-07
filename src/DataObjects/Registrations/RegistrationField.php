<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationField
{
    /** @param array<int|string, string> $options */
    public function __construct(
        public string $name,
        public string $label,
        public ?string $note,
        public bool $required,
        public string $type,
        public array $options,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        $options = Data::object($data, 'options');
        foreach ($options as $label) {
            if (! is_string($label)) {
                throw new HydrationException('Invalid registration field options.');
            }
        }

        return new self(
            name: Data::string($data, 'name'),
            label: Data::string($data, 'label'),
            note: Data::nullableString($data, 'note'),
            required: Data::bool($data, 'required'),
            type: Data::string($data, 'type'),
            options: $options,
        );
    }
}
