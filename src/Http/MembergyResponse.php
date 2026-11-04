<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Http;

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use SensitiveParameter;
use Throwable;

/** Retains a diagnostic-safe request and buffered response, without sender exception chains. */
final class MembergyResponse extends Response
{
    public function __construct(
        #[SensitiveParameter] ResponseInterface $psrResponse,
        #[SensitiveParameter] PendingRequest $pendingRequest,
        #[SensitiveParameter] RequestInterface $psrRequest,
        #[SensitiveParameter] ?Throwable $senderException = null,
    ) {
        unset($senderException); // Never retain the sender's credential-bearing exception chain.
        $connector = $pendingRequest->getConnector();
        assert($connector instanceof MembergyConnector);
        $body = (string) $psrResponse->getBody();
        $safeBody = $connector->redactCredentials($body);
        $psrResponse = $psrResponse->withBody($pendingRequest->getFactoryCollection()->streamFactory->createStream($safeBody));
        if ($body !== $safeBody) {
            $psrResponse = $psrResponse->withoutHeader('Content-Length');
        }
        foreach ($psrResponse->getHeaders() as $name => $values) {
            $psrResponse = $psrResponse->withHeader($name, array_map($connector->redactCredentials(...), $values));
        }
        parent::__construct($psrResponse, $pendingRequest, $psrRequest->withoutHeader('X-Membergy-Cms-Token'));
    }

    /** @return array<string, int> */
    public function __debugInfo(): array
    {
        return ['status' => $this->status()];
    }
}
