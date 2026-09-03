<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Connectors;

use InvalidArgumentException;
use Lacodix\MembergySdk\Cache\ResponseCache;
use Lacodix\MembergySdk\Cache\ResponseCacheMiddleware;
use Lacodix\MembergySdk\Compatibility;
use Lacodix\MembergySdk\Exceptions\MembergyException;
use Saloon\Enums\Method;
use Saloon\Enums\PipeOrder;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Traits\Plugins\HasTimeout;

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
final class MembergyConnector extends Connector
{
    use HasTimeout;

    protected string $baseUrl;

    protected float $connectTimeout;

    protected float $requestTimeout;

    public function __construct(
        string $baseUrl,
        protected string $tenant,
        protected ?string $userToken = null,
        protected string $apiVersion = 'v1',
        protected ?ResponseCache $responseCache = null,
        float $connectTimeout = 5.0,
        float $requestTimeout = 15.0,
        int $tries = 3,
        int $retryInterval = 200,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->connectTimeout = $connectTimeout;
        $this->requestTimeout = $requestTimeout;
        $this->tries = $tries;
        $this->retryInterval = $retryInterval;
        $this->useExponentialBackoff = true;

        if ($this->tenant === '') {
            throw new MembergyException('Tenant slug must not be empty.');
        }

        if ($connectTimeout <= 0 || $requestTimeout <= 0 || $tries < 1 || $retryInterval < 0) {
            throw new InvalidArgumentException('Membergy transport options are invalid.');
        }

        Compatibility::assertApiVersion($this->apiVersion);
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl.'/api/'.$this->apiVersion;
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

    public function boot(PendingRequest $pendingRequest): void
    {
        if ($this->responseCache === null) {
            return;
        }

        $middleware = new ResponseCacheMiddleware($this->responseCache, $this->cacheScope());
        $pendingRequest->middleware()
            ->onRequest($middleware, 'membergyResponseCache', PipeOrder::FIRST)
            ->onResponse($middleware->handleResponse(...), 'membergyResponseCache', PipeOrder::LAST);
    }

    public function handleRetry(
        FatalRequestException|RequestException $exception,
        Request $request,
    ): bool {
        if (! in_array($request->getMethod(), [Method::GET, Method::HEAD], true)) {
            return false;
        }

        if ($exception instanceof FatalRequestException) {
            return true;
        }

        $status = $exception->getResponse()->status();

        return $status === 429 || $status >= 500;
    }

    /**
     * Return a copy of this connector authenticated with the given
     * user bearer token. Safe to use per-request without mutating
     * shared state.
     */
    public function withUserToken(string $token): self
    {
        return $this->copyWithUserToken($token);
    }

    public function withoutUserToken(): self
    {
        return $this->copyWithUserToken(null);
    }

    private function copyWithUserToken(?string $token): self
    {
        $connector = new self(
            baseUrl: $this->baseUrl,
            tenant: $this->tenant,
            userToken: $token,
            apiVersion: $this->apiVersion,
            responseCache: $this->responseCache,
            connectTimeout: $this->connectTimeout,
            requestTimeout: $this->requestTimeout,
            tries: $this->tries ?? 3,
            retryInterval: $this->retryInterval ?? 200,
        );

        $mockClient = $this->getMockClient();
        if ($mockClient !== null) {
            $connector->withMockClient($mockClient);
        }

        return $connector;
    }

    public function tenant(): string
    {
        return $this->tenant;
    }

    public function hasUserToken(): bool
    {
        return $this->userToken !== null;
    }

    public function withResponseCache(ResponseCache $responseCache): self
    {
        $connector = new self(
            baseUrl: $this->baseUrl,
            tenant: $this->tenant,
            userToken: $this->userToken,
            apiVersion: $this->apiVersion,
            responseCache: $responseCache,
            connectTimeout: $this->connectTimeout,
            requestTimeout: $this->requestTimeout,
            tries: $this->tries ?? 3,
            retryInterval: $this->retryInterval ?? 200,
        );

        $mockClient = $this->getMockClient();
        if ($mockClient !== null) {
            $connector->withMockClient($mockClient);
        }

        return $connector;
    }

    public function responseCache(): ?ResponseCache
    {
        return $this->responseCache;
    }

    public function cacheScope(): string
    {
        return $this->userToken === null
            ? 'public'
            : 'member:'.hash('sha256', $this->userToken);
    }
}
