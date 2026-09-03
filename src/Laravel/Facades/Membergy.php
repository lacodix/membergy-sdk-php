<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Resources\ContentResource;

/**
 * @method static ContentResource content()
 * @method static MembergyClient withUserToken(string $token)
 * @method static MembergyClient withoutUserToken()
 *
 * @see MembergyClient
 */
class Membergy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MembergyClient::class;
    }
}
