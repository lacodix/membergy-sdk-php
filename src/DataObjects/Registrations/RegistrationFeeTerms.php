<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationFeeTerms
{
    public function __construct(public RegistrationFeeTerm $primary, public RegistrationFeeTerm $guardian) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            RegistrationFeeTerm::fromArray(Data::object($data, 'primary')),
            RegistrationFeeTerm::fromArray(Data::object($data, 'guardian')),
        );
    }
}
