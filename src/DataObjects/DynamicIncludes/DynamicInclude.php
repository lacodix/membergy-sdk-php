<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\DataObjects\DynamicIncludes;

interface DynamicInclude
{
    public function blockId(): string;

    public function type(): string;
}
