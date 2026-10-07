<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Registrations;

final readonly class RegistrationDocument
{
    public function __construct(public string $bytes, public string $contentType) {}

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['type' => self::class];
    }
}
