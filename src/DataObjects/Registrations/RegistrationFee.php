<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationFee
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $amount,
        public string $interval,
        public string $currency,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            id: Data::int($data, 'id'),
            name: Data::string($data, 'name'),
            amount: Data::nullableString($data, 'amount'),
            interval: Data::string($data, 'interval'),
            currency: Data::string($data, 'currency'),
        );
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['type' => self::class];
    }
}
