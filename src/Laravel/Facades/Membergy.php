<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Lacodix\MembergySdk\Media\ImageUrlBuilder;
use Lacodix\MembergySdk\Media\MediaUrlFactory;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Resources\AuthResource;
use Lacodix\MembergySdk\Resources\ContentResource;
use Lacodix\MembergySdk\Resources\MeResource;
use Lacodix\MembergySdk\Resources\NewsletterResource;
use Lacodix\MembergySdk\Resources\RegistrationResource;
use Psr\SimpleCache\CacheInterface;

/**
 * @method static ContentResource content()
 * @method static NewsletterResource newsletter()
 * @method static RegistrationResource registration()
 * @method static AuthResource auth()
 * @method static MeResource me()
 * @method static MembergyClient withUserToken(string $token)
 * @method static MembergyClient withoutUserToken()
 * @method static MembergyClient withCache(CacheInterface $publicStore, ?CacheInterface $memberStore = null, ?\Lacodix\MembergySdk\Cache\CacheOptions $options = null)
 * @method static void flush(?string $resource = null)
 * @method static array<string, string|int> compatibility()
 * @method static MediaUrlFactory mediaUrls()
 * @method static ImageUrlBuilder image(string $uuid)
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
