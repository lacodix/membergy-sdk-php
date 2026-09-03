<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk;

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\Resources\ContentResource;

/**
 * The primary entry point for the Membergy SDK.
 *
 * Construct it once per tenant — typically via dependency injection
 * (in Laravel through the bundled ServiceProvider, elsewhere by hand):
 *
 *   $client = new MembergyClient(
 *       baseUrl: 'https://members.example.org',
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
 *   $profile = $authed->content()->posts()->visibility('members')->get();
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
    ) {
        $this->connector = $connector ?? new MembergyConnector(
            baseUrl: $baseUrl,
            tenant: $tenant,
            userToken: $userToken,
            apiVersion: $apiVersion,
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

    public function withUserToken(string $token): self
    {
        return self::fromConnector($this->connector->withUserToken($token));
    }

    public function withoutUserToken(): self
    {
        return self::fromConnector($this->connector->withoutUserToken());
    }

    public function connector(): MembergyConnector
    {
        return $this->connector;
    }
}
