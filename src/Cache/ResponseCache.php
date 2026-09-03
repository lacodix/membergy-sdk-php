<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Cache;

use Psr\SimpleCache\CacheInterface;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;

final class ResponseCache
{
    private const STORED_HEADERS = [
        'Content-Type',
        'Cache-Control',
        'ETag',
        'Last-Modified',
        'Vary',
        'X-Membergy-Contract-Version',
    ];

    public function __construct(
        private readonly CacheInterface $publicStore,
        private readonly ?CacheInterface $memberStore = null,
        private readonly CacheOptions $options = new CacheOptions,
        private readonly CacheClock $clock = new SystemCacheClock,
    ) {}

    public function context(PendingRequest $pendingRequest, string $scope): ?CacheContext
    {
        $store = $this->storeForScope($scope);
        if ($store === null) {
            return null;
        }

        $resource = $this->resourceFor($pendingRequest);
        if ($this->options->ttlFor($resource) === 0) {
            return null;
        }

        $generation = $this->generation($store, 'all');
        $resourceGeneration = $this->generation($store, 'resource:'.$resource);
        $accept = $pendingRequest->headers()->get('Accept', 'application/json');
        $identity = implode('|', [
            $generation,
            $resourceGeneration,
            $scope,
            $pendingRequest->getMethod()->value,
            (string) $pendingRequest->getUri(),
            is_string($accept) ? $accept : 'application/json',
        ]);
        $key = $this->options->prefix.':response:'.hash('sha256', $identity);
        $stored = $store->get($key);
        $entry = is_array($stored) ? CacheEntry::fromArray($stored) : null;

        return new CacheContext($store, $key, $resource, $scope, $entry);
    }

    public function now(): int
    {
        return $this->clock->now();
    }

    public function forget(CacheContext $context): void
    {
        $context->store->delete($context->key);
    }

    public function store(CacheContext $context, Response $response): void
    {
        if ($response->status() !== 200 || ! $response->isJson()) {
            return;
        }

        $cacheControl = $this->headerLine($response, 'Cache-Control');
        $normalized = strtolower($cacheControl);

        if (str_contains($normalized, 'no-store')) {
            $this->forget($context);

            return;
        }

        $isPublic = $context->scope === 'public';
        if (
            ($isPublic && ! preg_match('/(?:^|,)\s*public(?:\s*,|$)/', $normalized))
            || (! $isPublic && ! str_contains($normalized, 'private'))
        ) {
            return;
        }

        $serverTtl = $this->serverTtl($normalized, $isPublic);
        $configuredTtl = $this->options->ttlFor($context->resource);
        if ($serverTtl <= 0 || $configuredTtl <= 0) {
            return;
        }

        $ttl = min($serverTtl, $configuredTtl);
        $staleTtl = min($this->options->staleTtl, $this->serverStaleTtl($normalized));
        $now = $this->now();
        $entry = new CacheEntry(
            body: $response->body(),
            status: $response->status(),
            headers: $this->storedHeaders($response),
            storedAt: $now,
            expiresAt: $now + $ttl,
            staleUntil: $now + $ttl + $staleTtl,
            ttl: $ttl,
            staleTtl: $staleTtl,
        );

        $context->store->set($context->key, $entry->toArray(), $ttl + $staleTtl);
    }

    public function touch(CacheContext $context, CacheEntry $entry): CacheEntry
    {
        $entry = $entry->revalidated($this->now());
        $context->store->set(
            $context->key,
            $entry->toArray(),
            $entry->ttl + $entry->staleTtl,
        );

        return $entry;
    }

    public function restore(Response $response, CacheEntry $entry): Response
    {
        $pendingRequest = $response->getPendingRequest();
        $factories = $pendingRequest->getFactoryCollection();
        $psrResponse = $factories->responseFactory
            ->createResponse($entry->status)
            ->withBody($factories->streamFactory->createStream($entry->body));

        foreach ($entry->headers as $name => $value) {
            $psrResponse = $psrResponse->withHeader($name, $value);
        }

        $responseClass = $pendingRequest->getResponseClass();
        $cached = $responseClass::fromPsrResponse(
            psrResponse: $psrResponse,
            pendingRequest: $pendingRequest,
            psrRequest: $response->getPsrRequest(),
        );
        $cached->setCached(true);

        return $cached;
    }

    public function invalidate(?string $resource = null): void
    {
        if ($resource !== null && preg_match('/^[a-z0-9-]+$/', $resource) !== 1) {
            throw new \InvalidArgumentException('The Membergy cache resource is invalid.');
        }

        $generation = $resource === null ? 'all' : 'resource:'.$resource;
        $stores = [$this->publicStore];
        if ($this->memberStore !== null && $this->memberStore !== $this->publicStore) {
            $stores[] = $this->memberStore;
        }

        foreach ($stores as $store) {
            $store->set($this->generationKey($generation), bin2hex(random_bytes(12)));
        }
    }

    public function resourceFor(PendingRequest $pendingRequest): string
    {
        $path = trim((string) parse_url($pendingRequest->getUrl(), PHP_URL_PATH), '/');

        if (preg_match(
            '#/content/(post-categories|pages|posts|menus|images?|files?|events|boilerplates|newsletter)(?:/|$)#',
            $path,
            $matches,
        ) === 1) {
            return match ($matches[1]) {
                'image', 'images' => 'images',
                'file', 'files' => 'files',
                default => $matches[1],
            };
        }

        if (str_contains($path, '/me/')) {
            return 'self-service';
        }

        if (str_contains($path, '/forms/')) {
            return 'forms';
        }

        if (str_contains($path, '/auth/') || str_ends_with($path, '/token')) {
            return 'auth';
        }

        return 'other';
    }

    /** @return array<string, string> */
    private function storedHeaders(Response $response): array
    {
        $headers = [];
        foreach (self::STORED_HEADERS as $name) {
            $value = $this->headerLine($response, $name);
            if ($value !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    private function headerLine(Response $response, string $name): string
    {
        return $response->getPsrResponse()->getHeaderLine($name);
    }

    private function serverTtl(string $cacheControl, bool $public): int
    {
        if ($public && preg_match('/(?:^|,)\s*s-maxage=(\d+)/', $cacheControl, $matches) === 1) {
            return (int) $matches[1];
        }

        return preg_match('/(?:^|,)\s*max-age=(\d+)/', $cacheControl, $matches) === 1
            ? (int) $matches[1]
            : 0;
    }

    private function serverStaleTtl(string $cacheControl): int
    {
        $values = [];
        foreach (['stale-while-revalidate', 'stale-if-error'] as $directive) {
            if (preg_match('/(?:^|,)\s*'.preg_quote($directive, '/').'=(\d+)/', $cacheControl, $matches) === 1) {
                $values[] = (int) $matches[1];
            }
        }

        return $values === [] ? 0 : max($values);
    }

    private function storeForScope(string $scope): ?CacheInterface
    {
        return $scope === 'public' ? $this->publicStore : $this->memberStore;
    }

    private function generation(CacheInterface $store, string $name): string
    {
        $value = $store->get($this->generationKey($name), '1');

        return is_string($value) || is_int($value) ? (string) $value : '1';
    }

    private function generationKey(string $name): string
    {
        return $this->options->prefix.':generation:'.$name;
    }
}
