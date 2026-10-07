<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\Exceptions\RegistrationException;
use Lacodix\MembergySdk\Exceptions\RegistrationIdentityException;
use Lacodix\MembergySdk\Exceptions\RegistrationPayloadException;
use Lacodix\MembergySdk\Exceptions\RegistrationTransportException;
use Lacodix\MembergySdk\Requests\Registrations\AbstractRegistrationRequest;
use Lacodix\MembergySdk\Support\Payload;
use Saloon\Http\Request;
use Saloon\Http\Response;
use SensitiveParameter;
use Throwable;

abstract class AbstractRegistrationResource extends AbstractResource
{
    protected function send(#[SensitiveParameter] Request $request): Response
    {
        if (! $request instanceof AbstractRegistrationRequest) {
            throw new RegistrationTransportException('Unsupported registration transport.');
        }
        try {
            $response = $this->connector->send($request);
        } catch (RegistrationIdentityException) {
            // Detach the trace from Saloon's PendingRequest construction and its credential-bearing arguments.
            throw new RegistrationIdentityException('Invalid registration identity context for this request.');
        } catch (Throwable) {
            throw new RegistrationTransportException('The registration transport failed.');
        }
        if (! $response->successful()) {
            throw RegistrationException::fromResponse($response);
        }

        return $response;
    }

    /** @template T
     * @param  callable(array<string, mixed>): T  $hydrate
     * @return T
     */
    protected function hydrate(#[SensitiveParameter] Response $response, callable $hydrate, bool $wrapped = true): mixed
    {
        try {
            $payload = Payload::fromResponse($response);

            return $hydrate($wrapped ? Payload::data($payload) : $payload);
        } catch (Throwable) {
            throw new RegistrationPayloadException('The registration response does not match its contract.');
        }
    }
}
