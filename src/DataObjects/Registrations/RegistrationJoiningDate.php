<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Support\Data;

final readonly class RegistrationJoiningDate
{
    /** @param list<RegistrationJoiningDateOption> $options */
    public function __construct(public string $mode, public string $default, public array $options) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::string($data, 'mode'), Data::string($data, 'default'),
            array_map(RegistrationJoiningDateOption::fromArray(...), Data::objectList($data, 'options')),
        );
    }
}
