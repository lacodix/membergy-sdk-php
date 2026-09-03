<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\Blocks;

interface Block
{
    public function id(): string;

    public function type(): string;

    public function version(): int;
}
