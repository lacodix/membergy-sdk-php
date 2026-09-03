<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Cache;

final readonly class CacheEntry
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $body,
        public int $status,
        public array $headers,
        public int $storedAt,
        public int $expiresAt,
        public int $staleUntil,
        public int $ttl,
        public int $staleTtl,
    ) {}

    /** @param array<string, mixed> $value */
    public static function fromArray(array $value): ?self
    {
        $headers = $value['headers'] ?? null;
        if (! is_array($headers)) {
            return null;
        }

        $normalizedHeaders = [];
        foreach ($headers as $name => $header) {
            if (! is_string($name) || ! is_string($header)) {
                return null;
            }

            $normalizedHeaders[$name] = $header;
        }

        $body = $value['body'] ?? null;
        $status = $value['status'] ?? null;
        $storedAt = $value['stored_at'] ?? null;
        $expiresAt = $value['expires_at'] ?? null;
        $staleUntil = $value['stale_until'] ?? null;
        $ttl = $value['ttl'] ?? null;
        $staleTtl = $value['stale_ttl'] ?? null;

        if (
            ! is_string($body)
            || ! is_int($status)
            || ! is_int($storedAt)
            || ! is_int($expiresAt)
            || ! is_int($staleUntil)
            || ! is_int($ttl)
            || ! is_int($staleTtl)
        ) {
            return null;
        }

        return new self(
            body: $body,
            status: $status,
            headers: $normalizedHeaders,
            storedAt: $storedAt,
            expiresAt: $expiresAt,
            staleUntil: $staleUntil,
            ttl: $ttl,
            staleTtl: $staleTtl,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'body' => $this->body,
            'status' => $this->status,
            'headers' => $this->headers,
            'stored_at' => $this->storedAt,
            'expires_at' => $this->expiresAt,
            'stale_until' => $this->staleUntil,
            'ttl' => $this->ttl,
            'stale_ttl' => $this->staleTtl,
        ];
    }

    public function isFresh(int $now): bool
    {
        return $this->expiresAt > $now;
    }

    public function isStaleUsable(int $now): bool
    {
        return $this->staleUntil > $now;
    }

    public function revalidated(int $now): self
    {
        return new self(
            body: $this->body,
            status: $this->status,
            headers: $this->headers,
            storedAt: $now,
            expiresAt: $now + $this->ttl,
            staleUntil: $now + $this->ttl + $this->staleTtl,
            ttl: $this->ttl,
            staleTtl: $this->staleTtl,
        );
    }
}
