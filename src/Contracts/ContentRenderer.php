<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Contracts;

use Lacodix\MembergySdk\DataObjects\BlockDocument;
use Lacodix\MembergySdk\DataObjects\DynamicIncludes;

interface ContentRenderer
{
    public function render(BlockDocument $document, ?DynamicIncludes $included = null): string;
}
