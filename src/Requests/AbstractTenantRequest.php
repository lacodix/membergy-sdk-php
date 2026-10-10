<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests;

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Saloon\Helpers\Debugger;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

abstract class AbstractTenantRequest extends Request
{
    public function __construct(protected string $tenant) {}

    /** @param callable(PendingRequest, RequestInterface): void|null $onRequest */
    public function debugRequest(?callable $onRequest = null, bool $die = false): static
    {
        $debugger = $onRequest ?? Debugger::symfonyRequestDebugger(...);

        return parent::debugRequest(static function (PendingRequest $pending, RequestInterface $message) use ($debugger): void {
            if (! self::authenticated($pending, $message)) {
                $debugger($pending, $message);
            }
        }, $die);
    }

    /** @param callable(Response, ResponseInterface): void|null $onResponse */
    public function debugResponse(?callable $onResponse = null, bool $die = false): static
    {
        $debugger = $onResponse ?? Debugger::symfonyResponseDebugger(...);

        return parent::debugResponse(static function (Response $response, ResponseInterface $message) use ($debugger): void {
            if (! self::authenticated($response->getPendingRequest(), $response->getPsrRequest())) {
                $debugger($response, $message);
            }
        }, $die);
    }

    private static function authenticated(PendingRequest $pending, RequestInterface $message): bool
    {
        $connector = $pending->getConnector();

        return $message->hasHeader('Authorization')
            || ($connector instanceof MembergyConnector && $connector->hasUserToken());
    }

    protected function tenantEndpoint(string $path): string
    {
        return '/tenant/'.rawurlencode($this->tenant).'/'.ltrim($path, '/');
    }
}
