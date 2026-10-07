<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationFees
{
    /** @param list<RegistrationFee> $primary
     * @param  list<RegistrationFee>  $guardian
     */
    public function __construct(public array $primary, public array $guardian) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            array_map(RegistrationFee::fromArray(...), Data::objectList($data, 'primary')),
            array_map(RegistrationFee::fromArray(...), Data::objectList($data, 'guardian')),
        );
    }
}
