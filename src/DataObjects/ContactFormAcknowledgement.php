<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Support\Data;

final readonly class ContactFormAcknowledgement
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public string $status,
        public string $message,
        public array $extra = [],
    ) {
        if ($this->status !== 'accepted') {
            throw new HydrationException('Invalid contact form acknowledgement.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            status: Data::string($data, 'status'),
            message: Data::string($data, 'message'),
            extra: Data::extra($data, ['status', 'message']),
        );
    }
}
