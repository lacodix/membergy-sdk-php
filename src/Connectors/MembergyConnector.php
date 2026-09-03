<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Connectors;

use Lacodix\MembergySdk\Exceptions\MembergyException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;

/**
 * The Membergy API connector.
 *
 * Holds the base URL, the tenant slug and optional auth tokens for
 * talking to a single Membergy instance. Create one per tenant.
 *
 * Authentication modes:
 *  - Public (no token): only public content endpoints under
 *    /api/tenant/{tenant}/content/...
 *  - User token (sanctum, obtained via POST /api/token): unlocks
 *    internal content, resources, feature flags, permissions.
 *
 * The connector is immutable in its base URL and tenant. Token state
 * is toggled via ->withUserToken() which returns a NEW instance.
 */
class MembergyConnector extends Connector
{
    protected string $baseUrl;

    public function __construct(
        string $baseUrl,
        protected string $tenant,
        protected ?string $userToken = null,
        protected string $apiVersion = 'v1',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');

        if ($this->tenant === '') {
            throw new MembergyException('Tenant slug must not be empty.');
        }
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl . '/api/' . $this->apiVersion;
    }

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
        ];
    }

    protected function defaultAuth(): ?TokenAuthenticator
    {
        return $this->userToken !== null
            ? new TokenAuthenticator($this->userToken)
            : null;
    }

    /**
     * Return a copy of this connector authenticated with the given
     * user bearer token. Safe to use per-request without mutating
     * shared state.
     */
    public function withUserToken(string $token): static
    {
        return new static(
            baseUrl: $this->baseUrl,
            tenant: $this->tenant,
            userToken: $token,
            apiVersion: $this->apiVersion,
        );
    }

    public function withoutUserToken(): static
    {
        return new static(
            baseUrl: $this->baseUrl,
            tenant: $this->tenant,
            userToken: null,
            apiVersion: $this->apiVersion,
        );
    }

    public function tenant(): string
    {
        return $this->tenant;
    }

    public function hasUserToken(): bool
    {
        return $this->userToken !== null;
    }
}
