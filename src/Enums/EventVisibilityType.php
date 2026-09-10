<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Enums;

enum EventVisibilityType: string
{
    case PUBLIC = 'public';
    case MEMBERS = 'members';
    case PARTICIPANTS = 'participants';
}
