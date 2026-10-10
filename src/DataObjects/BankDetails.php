<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class BankDetails
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public ?string $name,
        public ?string $iban,
        public ?string $bic,
        public ?string $accountHolder,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Data::nullableString($data, 'name'), Data::nullableString($data, 'iban'),
            Data::nullableString($data, 'bic'), Data::nullableString($data, 'account_holder'),
            Data::extra($data, ['name', 'iban', 'bic', 'account_holder']),
        );
    }
}
