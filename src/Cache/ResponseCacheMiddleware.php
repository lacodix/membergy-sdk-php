<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Cache;

use Saloon\Enums\Method;
use Saloon\Http\Faking\FakeResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Throwable;

final class ResponseCacheMiddleware
{
    private ?CacheContext $context = null;

    private ?CacheEntry $entry = null;

    private bool $cacheHit = false;

    private Method $method;

    private string $resource = 'other';

    public function __construct(
        private readonly ResponseCache $cache,
        private readonly string $scope,
    ) {}

    public function __invoke(PendingRequest $pendingRequest): PendingRequest|FakeResponse
    {
        $this->method = $pendingRequest->getMethod();
        $this->resource = $this->cache->resourceFor($pendingRequest);

        if (! in_array($this->method, [Method::GET, Method::HEAD], true)) {
            return $pendingRequest;
        }

        try {
            $this->context = $this->cache->context($pendingRequest, $this->scope);
            $this->entry = $this->context?->entry;
        } catch (Throwable) {
            return $pendingRequest;
        }

        if ($this->context === null || $this->entry === null) {
            return $pendingRequest;
        }

        $now = $this->cache->now();
        if ($this->entry->isFresh($now)) {
            $this->cacheHit = true;

            return new FakeResponse(
                $this->entry->body,
                $this->entry->status,
                $this->entry->headers,
            );
        }

        if (! $this->entry->isStaleUsable($now)) {
            try {
                $this->cache->forget($this->context);
            } catch (Throwable) {
                // A cache failure must never make the API unavailable.
            }

            $this->entry = null;

            return $pendingRequest;
        }

        $etag = $this->entry->headers['ETag'] ?? null;
        if (is_string($etag) && $etag !== '') {
            $pendingRequest->headers()->add('If-None-Match', $etag);
        }

        $lastModified = $this->entry->headers['Last-Modified'] ?? null;
        if (is_string($lastModified) && $lastModified !== '') {
            $pendingRequest->headers()->add('If-Modified-Since', $lastModified);
        }

        return $pendingRequest;
    }

    public function handleResponse(Response $response): Response
    {
        if (! in_array($this->method, [Method::GET, Method::HEAD], true)) {
            if ($response->successful()) {
                try {
                    $this->cache->invalidate($this->resource);
                } catch (Throwable) {
                    // Writes must succeed even if consumer-side invalidation fails.
                }
            }

            return $response;
        }

        if ($this->cacheHit) {
            return $response->setCached(true);
        }

        if ($this->context === null) {
            return $response;
        }

        if ($response->status() === 304 && $this->entry !== null) {
            try {
                return $this->cache->restore(
                    $response,
                    $this->cache->touch($this->context, $this->entry),
                );
            } catch (Throwable) {
                return $response;
            }
        }

        if ($response->serverError() && $this->entry?->isStaleUsable($this->cache->now())) {
            try {
                return $this->cache->restore($response, $this->entry);
            } catch (Throwable) {
                return $response;
            }
        }

        try {
            $this->cache->store($this->context, $response);
        } catch (Throwable) {
            // Cache stores are an optimization and deliberately fail open.
        }

        return $response;
    }
}
