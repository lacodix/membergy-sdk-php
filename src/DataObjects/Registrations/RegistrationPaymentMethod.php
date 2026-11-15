<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Enums\RegistrationPaymentMethodType;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

final readonly class RegistrationPaymentMethod
{
    public function __construct(
        public int $id,
        public string $title,
        public RegistrationPaymentMethodType $type,
        public ?int $collectionIntervalMonths = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        $type = RegistrationPaymentMethodType::tryFrom(Data::string($data, 'type'));
        $id = Data::int($data, 'id');
        if ($type === null || $id < 1) {
            throw new HydrationException('Invalid registration payment method.');
        }

        $interval = Data::nullableInt($data, 'collection_interval_months');
        if ($interval !== null && $interval < 1) {
            throw new HydrationException('Invalid registration collection interval.');
        }

        return new self($id, Data::string($data, 'title'), $type, $interval);
    }
}
