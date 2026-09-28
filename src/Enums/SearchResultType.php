<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Enums;

enum SearchResultType: string
{
    case PAGE = 'page';
    case POST = 'post';
    case EVENT = 'event';
}
