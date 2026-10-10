<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;

trait RedactsSensitivePayload
{
    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['endpoint' => $this->resolveEndpoint(), 'payload' => '[redacted]'];
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
}
