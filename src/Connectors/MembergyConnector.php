<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Connectors;

use GuzzleHttp\Promise\PromiseInterface;
use InvalidArgumentException;
use Lacodix\MembergySdk\Cache\ResponseCache;
use Lacodix\MembergySdk\Cache\ResponseCacheMiddleware;
use Lacodix\MembergySdk\Compatibility;
use Lacodix\MembergySdk\Exceptions\CmsAuthenticationException;
use Lacodix\MembergySdk\Exceptions\MembergyException;
use Lacodix\MembergySdk\Exceptions\RegistrationIdentityException;
use Lacodix\MembergySdk\Exceptions\TransportException;
use Lacodix\MembergySdk\Exceptions\UnsafeRequestException;
use Lacodix\MembergySdk\Http\MembergyPendingRequest;
use Lacodix\MembergySdk\Requests\Content\AbstractContactFormRequest;
use Lacodix\MembergySdk\Requests\Registrations\AbstractRegistrationRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationOfferRequest;
use Lacodix\MembergySdk\Requests\Registrations\SubmitRegistrationApplicationRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Saloon\Enums\Method;
use Saloon\Enums\PipeOrder;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Http\Senders\GuzzleSender;
use Saloon\Traits\Plugins\HasTimeout;
use SensitiveParameter;

/**
 * The Membergy API connector.
 *
 * Holds the base URL, the tenant slug, required server CMS credential and optional visitor token for
 * talking to a single Membergy instance. Create one per tenant.
 *
 * Authentication modes:
 *  - Guest (CMS credential, no visitor bearer): public content endpoints under
 *    /api/v1/tenant/{tenant}/content/...
 *  - Website token (Sanctum, obtained via POST /api/v1/token): unlocks
 *    member CMS, own-data self-service and account-bound registration.
 *    Generic administration APIs remain unavailable to these tokens.
 *
 * The connector is immutable in its base URL and tenant. Token state
 * is toggled via ->withUserToken() which returns a NEW instance.
 */
final class MembergyConnector extends Connector
{
    use HasTimeout;

    // Keep credential-bearing requests out of Laravel sender events/automatic telemetry.
    protected string $defaultSender = GuzzleSender::class;

    protected string $baseUrl;

    protected float $connectTimeout;

    protected float $requestTimeout;

    private bool $staticUserToken = false;

    private readonly \SensitiveParameterValue $cmsCredential;

    public function __construct(
        string $baseUrl,
        protected string $tenant,
        #[SensitiveParameter] private readonly ?string $userToken = null,
        protected string $apiVersion = 'v1',
        protected ?ResponseCache $responseCache = null,
        float $connectTimeout = 5.0,
        float $requestTimeout = 15.0,
        int $tries = 3,
        int $retryInterval = 200,
        #[SensitiveParameter] ?string $cmsToken = null,
    ) {
        $this->cmsCredential = new \SensitiveParameterValue($cmsToken);
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
        $this->origin($this->baseUrl);
        if (parse_url($this->baseUrl, PHP_URL_QUERY) !== null || parse_url($this->baseUrl, PHP_URL_FRAGMENT) !== null) {
            throw new UnsafeRequestException;
        }
    }

    public function resolveBaseUrl(): string
    {
        return $this->baseUrl.'/api/'.$this->apiVersion;
    }

    private function cmsToken(): ?string
    {
        $value = $this->cmsCredential->getValue();

        return is_string($value) ? $value : null;
    }

    public function createPendingRequest(#[SensitiveParameter] Request $request, ?MockClient $mockClient = null): PendingRequest
    {
        if ($this->cmsToken() === null || trim($this->cmsToken()) === '' || preg_match('/[\x00-\x20\x7F]/', $this->cmsToken()) === 1) {
            throw new CmsAuthenticationException;
        }

        return new MembergyPendingRequest($this, $request, $mockClient);
    }

    /** @param callable(\Throwable, Request): bool|null $handleRetry */
    public function send(#[SensitiveParameter] Request $request, ?MockClient $mockClient = null, ?callable $handleRetry = null): Response
    {
        try {
            return parent::send($request, $mockClient, $handleRetry);
        } catch (FatalRequestException) {
            throw new TransportException;
        }
    }

    public function sendAsync(#[SensitiveParameter] Request $request, ?MockClient $mockClient = null): PromiseInterface
    {
        return parent::sendAsync($request, $mockClient)->otherwise(static function (\Throwable $exception): never {
            if ($exception instanceof FatalRequestException) {
                throw new TransportException;
            }

            throw $exception;
        });
    }

    /** @internal Transport boundary; never use the returned request for logging. */
    public function authenticateCmsRequest(#[SensitiveParameter] RequestInterface $request): RequestInterface
    {
        $this->assertTrustedUri($request->getUri());
        $this->assertVisitorAuthorization($request->getHeaderLine('Authorization'));
        if (strtolower($request->getHeaderLine('Host')) !== strtolower($request->getUri()->getAuthority())) {
            throw new UnsafeRequestException;
        }

        return $request->withHeader('X-Membergy-Cms-Token', $this->cmsToken() ?? '');
    }

    private function assertVisitorAuthorization(#[SensitiveParameter] mixed $value): void
    {
        $expected = $this->userToken === null ? '' : 'Bearer '.$this->userToken;
        if (($value ?? '') !== $expected) {
            throw new UnsafeRequestException;
        }
    }

    /** @internal */
    public function assertTrustedUri(UriInterface $uri): void
    {
        if ($this->origin((string) $uri) !== $this->origin($this->baseUrl) || $uri->getUserInfo() !== '' || $uri->getFragment() !== '') {
            throw new UnsafeRequestException;
        }
        $decoded = rawurldecode((string) $uri);
        if ($this->redactCredentials($decoded) !== $decoded) {
            throw new UnsafeRequestException;
        }
    }

    /** @return array{string, string, int} */
    private function origin(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || str_contains($url, '\\')) {
            throw new UnsafeRequestException;
        }

        return [strtolower($parts['scheme']), strtolower($parts['host']), $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80)];
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
        $pendingRequest->middleware()->onRequest(function (PendingRequest $pending): void {
            $this->assertTrustedUri($pending->getUri());
            $this->assertVisitorAuthorization($pending->headers()->get('Authorization'));
            $pending->config()->add('allow_redirects', false);
        }, 'membergyTrustedOrigin', PipeOrder::FIRST);
        $request = $pendingRequest->getRequest();
        if ($this->staticUserToken && ($request instanceof ShowRegistrationOfferRequest || $request instanceof SubmitRegistrationApplicationRequest)) {
            throw new RegistrationIdentityException('A static server token cannot identify a registration applicant.');
        }
        if ($this->responseCache === null || ($request instanceof AbstractRegistrationRequest || $request instanceof AbstractContactFormRequest)) {
            return;
        }

        $middleware = new ResponseCacheMiddleware($this->responseCache, $this->cacheScope(), hash('sha256', $this->cmsToken() ?? ''));
        $pendingRequest->middleware()
            ->onRequest($middleware, 'membergyResponseCache', PipeOrder::FIRST)
            ->onResponse($middleware->handleResponse(...), 'membergyResponseCache', PipeOrder::LAST);
    }

    /** @param callable(PendingRequest, RequestInterface): void|null $onRequest */
    public function debugRequest(?callable $onRequest = null, bool $die = false): static
    {
        return parent::debugRequest(static function (): void {}, $die);
    }

    /** @param callable(Response, ResponseInterface): void|null $onResponse */
    public function debugResponse(?callable $onResponse = null, bool $die = false): static
    {
        return parent::debugResponse(static function (): void {}, $die);
    }

    public function __debugInfo(): array
    {
        return ['base_url' => $this->baseUrl, 'tenant' => $this->tenant, 'has_user_token' => $this->userToken !== null];
    }

    /** @return array<never, never> */
    public function __serialize(): array
    {
        throw new \LogicException('Membergy clients cannot be serialized.');
    }

    /** @internal Redact reflected credentials before retaining a response. */
    public function redactCredentials(#[SensitiveParameter] string $value): string
    {
        foreach ([$this->cmsToken(), $this->userToken] as $secret) {
            if ($secret !== null && $secret !== '') {
                $encoded = json_encode($secret);
                $value = str_replace([$secret, rawurlencode($secret), is_string($encoded) ? substr($encoded, 1, -1) : $secret], '[redacted]', $value);
            }
        }

        return $value;
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
    public function withUserToken(#[SensitiveParameter] string $token): self
    {
        return $this->copyWithUserToken($token);
    }

    /** Static server credentials remain usable for CMS, but never identify a registration applicant. */
    public function withStaticUserToken(#[SensitiveParameter] string $token): self
    {
        $connector = $this->copyWithUserToken($token);
        $connector->staticUserToken = true;

        return $connector;
    }

    public function withoutUserToken(): self
    {
        return $this->copyWithUserToken(null);
    }

    private function copyWithUserToken(#[SensitiveParameter] ?string $token): self
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
            cmsToken: $this->cmsToken(),
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
            cmsToken: $this->cmsToken(),
        );
        $connector->staticUserToken = $this->staticUserToken;

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
