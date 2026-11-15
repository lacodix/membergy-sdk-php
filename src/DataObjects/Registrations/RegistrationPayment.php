<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

/** Public offer presentation; contains no stored bank account or mandate state. */
final readonly class RegistrationPayment
{
    /** @param list<RegistrationPaymentMethod> $methods */
    public function __construct(
        public bool $requiredIfBillable,
        public array $methods,
        public ?string $mandateText,
        public ?string $mandateTextVersion,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            Data::bool($data, 'required_if_billable'),
            array_map(RegistrationPaymentMethod::fromArray(...), Data::objectList($data, 'methods')),
            Data::nullableString($data, 'mandate_text'),
            Data::nullableString($data, 'mandate_text_version'),
        );
    }
}
