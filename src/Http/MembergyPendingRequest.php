<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Http;

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Psr\Http\Message\RequestInterface;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;

/** Applies credentials only after both Saloon PSR hooks have finished. */
final class MembergyPendingRequest extends PendingRequest
{
    /** @return class-string<Response> */
    public function getResponseClass(): string
    {
        return MembergyResponse::class;
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['request' => $this->getRequest()::class];
    }

    public function createPsrRequest(): RequestInterface
    {
        $request = parent::createPsrRequest();
        $this->config()->merge(['allow_redirects' => false, 'debug' => false, 'on_stats' => null, 'on_headers' => null]);
        $connector = $this->getConnector();
        assert($connector instanceof MembergyConnector);

        return $connector->authenticateCmsRequest($request);
    }
}
