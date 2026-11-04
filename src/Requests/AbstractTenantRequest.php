<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

abstract class AbstractTenantRequest extends Request
{
    public function __construct(protected string $tenant) {}

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

    protected function tenantEndpoint(string $path): string
    {
        return '/tenant/'.rawurlencode($this->tenant).'/'.ltrim($path, '/');
    }
}
