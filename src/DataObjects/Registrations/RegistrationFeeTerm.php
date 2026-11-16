<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Support\Data;
use SensitiveParameter;

/** Server-defined contribution terms for one participant role. */
final readonly class RegistrationFeeTerm
{
    public function __construct(
        public string $mode,
        public ?string $note,
        public bool $requiresPayment,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        $mode = Data::string($data, 'mode');
        if (! in_array($mode, ['free', 'specified', 'deferred'], true)) {
            throw new HydrationException('Invalid registration fee mode.');
        }

        return new self($mode, Data::nullableString($data, 'note'), Data::bool($data, 'requires_payment'));
    }
}
