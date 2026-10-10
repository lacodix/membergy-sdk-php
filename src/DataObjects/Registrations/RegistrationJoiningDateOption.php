<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Support\Data;

final readonly class RegistrationJoiningDateOption
{
    public function __construct(public string $value, public string $label) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(Data::string($data, 'value'), Data::string($data, 'label'));
    }
}
