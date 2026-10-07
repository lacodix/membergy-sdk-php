<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationOption
{
    public function __construct(
        public int $id,
        public string $type,
        public string $label,
        public ?string $note,
        public bool $selectable,
        public bool $preselected,
        public int $position,
        public RegistrationFees $fees,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            Data::int($data, 'id'), Data::string($data, 'type'), Data::string($data, 'label'),
            Data::nullableString($data, 'note'), Data::bool($data, 'selectable'),
            Data::bool($data, 'preselected'), Data::int($data, 'position'),
            RegistrationFees::fromArray(Data::object($data, 'fees')),
        );
    }
}
