<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects;

final readonly class FormRow
{
    /** @param array<string, mixed> $values */
    public function __construct(public string $key, public array $values) {}
}
