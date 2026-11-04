<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

use Lacodix\MembergySdk\Support\Data;

final readonly class ContactFormMessages
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $success,
        public string $error,
        public array $extra = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            success: Data::string($data, 'success'),
            error: Data::string($data, 'error'),
            extra: Data::extra($data, ['success', 'error']),
        );
    }
}
