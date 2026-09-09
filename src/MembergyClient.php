<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk;

use Lacodix\MembergySdk\Cache\CacheOptions;
use Lacodix\MembergySdk\Cache\ResponseCache;
use Lacodix\MembergySdk\Cache\SystemCacheClock;
use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\Media\ImageUrlBuilder;
use Lacodix\MembergySdk\Media\MediaUrlFactory;
use Lacodix\MembergySdk\Resources\AuthResource;
use Lacodix\MembergySdk\Resources\ContentResource;
use Lacodix\MembergySdk\Resources\MeResource;
use Lacodix\MembergySdk\Resources\NewsletterResource;
use Lacodix\MembergySdk\Resources\RegistrationResource;
use Psr\SimpleCache\CacheInterface;

/**
 * The primary entry point for the Membergy SDK.
 *
 * Construct it once per tenant — typically via dependency injection
 * (in Laravel through the bundled ServiceProvider, elsewhere by hand):
 *
 *   $client = new MembergyClient(
 *       baseUrl: 'https://membergy.app',
 *       tenant:  'my-club',
 *   );
 *
 *   $posts = $client->content()->posts()->get();
 *
 * For authenticated per-user access (e.g. an end-user logged into
 * the tenant website, viewing members-only content), obtain a
 * bearer token and switch the client:
 *
 *   $authed = $client->withUserToken($token);
 *   $profile = $authed->me()->person()->get();
 *
 * The client itself is immutable: withUserToken() returns a new
 * instance. This makes it safe to use in long-lived containers
 * (Octane, queue workers) without token leakage between requests.
 */
class MembergyClient
{
    private readonly MembergyConnector $connector;

    public function __construct(
        string $baseUrl,
        string $tenant,
        ?string $userToken = null,
        string $apiVersion = 'v1',
        ?MembergyConnector $connector = null,
        ?ResponseCache $responseCache = null,
        float $connectTimeout = 5.0,
        float $requestTimeout = 15.0,
        int $tries = 3,
        int $retryInterval = 200,
    ) {
        $this->connector = $connector ?? new MembergyConnector(
            baseUrl: $baseUrl,
            tenant: $tenant,
            userToken: $userToken,
            apiVersion: $apiVersion,
            responseCache: $responseCache,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            tries: $tries,
            retryInterval: $retryInterval,
        );
    }

    public static function fromConnector(MembergyConnector $connector): self
    {
        // Constructor args are ignored because $connector is provided,
        // but PHP requires valid scalars for the declared types.
        return new self(
            baseUrl: 'unused',
            tenant: $connector->tenant(),
            connector: $connector,
        );
    }

    public function content(): ContentResource
    {
        return new ContentResource($this->connector);
    }

    public function newsletter(): NewsletterResource
    {
        return new NewsletterResource($this->connector);
    }

    public function registration(): RegistrationResource
    {
        return new RegistrationResource($this->connector);
    }

    public function auth(): AuthResource
    {
        return new AuthResource($this->connector);
    }

    public function me(): MeResource
    {
        return new MeResource($this->connector);
    }

    public function withUserToken(string $token): self
    {
        return self::fromConnector($this->connector->withUserToken($token));
    }

    public function withoutUserToken(): self
    {
        return self::fromConnector($this->connector->withoutUserToken());
    }

    public function withCache(
        CacheInterface $publicStore,
        ?CacheInterface $memberStore = null,
        ?CacheOptions $options = null,
    ): self {
        return self::fromConnector($this->connector->withResponseCache(new ResponseCache(
            publicStore: $publicStore,
            memberStore: $memberStore,
            options: $options ?? new CacheOptions,
            clock: new SystemCacheClock,
        )));
    }

    public function flush(?string $resource = null): void
    {
        $this->connector->responseCache()?->invalidate($resource);
    }

    /** @return array<string, string|int> */
    public function compatibility(): array
    {
        return Compatibility::matrix();
    }

    public function mediaUrls(): MediaUrlFactory
    {
        return new MediaUrlFactory(
            $this->connector->resolveBaseUrl(),
            $this->connector->tenant(),
        );
    }

    public function image(string $uuid): ImageUrlBuilder
    {
        return $this->mediaUrls()->image($uuid);
    }

    public function connector(): MembergyConnector
    {
        return $this->connector;
    }
}
