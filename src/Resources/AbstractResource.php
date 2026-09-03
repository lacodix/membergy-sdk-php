<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\Exceptions\AuthenticationException;
use Lacodix\MembergySdk\Exceptions\InvalidCredentialsException;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Exceptions\SelfServiceForbiddenException;
use Lacodix\MembergySdk\Exceptions\TwoFactorRequiredException;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Support\Payload;
use Saloon\Exceptions\Request\ClientException;
use Saloon\Exceptions\Request\Statuses\NotFoundException as SaloonNotFoundException;
use Saloon\Exceptions\Request\Statuses\TooManyRequestsException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Saloon\Exceptions\Request\Statuses\UnprocessableEntityException;
use Saloon\Http\Request;
use Saloon\Http\Response;

/** @phpstan-consistent-constructor */
abstract class AbstractResource
{
    /** @param array<string, mixed> $query */
    public function __construct(
        protected readonly MembergyConnector $connector,
        protected readonly array $query = [],
    ) {}

    /**
     * @template T
     *
     * @param  callable(array<string, mixed>): T  $hydrate
     * @return PaginatedResult<T>
     */
    protected function paginate(Request $request, callable $hydrate): PaginatedResult
    {
        $payload = Payload::fromResponse($this->send($request));

        return new PaginatedResult(
            items: array_map($hydrate, Payload::collection($payload)),
            meta: Data::object($payload, 'meta'),
            links: Data::object($payload, 'links'),
        );
    }

    /** @param array<string, mixed> $query */
    protected function withQuery(array $query): static
    {
        return new static($this->connector, [...$this->query, ...$query]);
    }

    protected function send(Request $request): Response
    {
        try {
            return $this->connector->send($request)->throw();
        } catch (UnauthorizedException $exception) {
            if ($this->errorCode($exception->getResponse()->json()) === 'auth_invalid_credentials') {
                throw new InvalidCredentialsException(
                    'The Membergy API rejected the supplied credentials.',
                    previous: $exception,
                );
            }

            throw new AuthenticationException(
                'The Membergy API rejected the supplied user token.',
                previous: $exception,
            );
        } catch (UnprocessableEntityException $exception) {
            throw new RequestValidationException(
                $this->validationErrors($exception->getResponse()->json()),
                previous: $exception,
            );
        } catch (ClientException $exception) {
            $code = $this->errorCode($exception->getResponse()->json());

            if ($exception instanceof TooManyRequestsException) {
                throw new RateLimitException(
                    retryAfterSeconds: $this->retryAfterSeconds(
                        $exception->getResponse()->header('Retry-After'),
                    ),
                    previous: $exception,
                );
            }

            if ($code === 'auth_two_factor_required') {
                throw new TwoFactorRequiredException(
                    'The Membergy account requires interactive two-factor authentication.',
                    previous: $exception,
                );
            }

            if ($code === 'self_service_forbidden') {
                throw new SelfServiceForbiddenException(
                    'The Membergy API rejected the requested self-service operation.',
                    previous: $exception,
                );
            }

            if ($code === 'self_service_not_found') {
                throw new ResourceNotFoundException(
                    'The requested Membergy self-service resource was not found.',
                    previous: $exception,
                );
            }

            if ($exception instanceof SaloonNotFoundException) {
                throw new ResourceNotFoundException(
                    'The requested Membergy resource was not found.',
                    previous: $exception,
                );
            }

            throw $exception;
        }
    }

    private function errorCode(mixed $payload): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        $code = $payload['code'] ?? null;

        return is_string($code) ? $code : null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function validationErrors(mixed $payload): array
    {
        if (! is_array($payload) || ! is_array($payload['errors'] ?? null)) {
            return [];
        }

        $errors = [];
        foreach ($payload['errors'] as $field => $messages) {
            if (! is_string($field) || ! is_array($messages)) {
                continue;
            }

            $validMessages = array_values(array_filter($messages, 'is_string'));
            if ($validMessages !== []) {
                $errors[$field] = $validMessages;
            }
        }

        return $errors;
    }

    /** @param string|array<array-key, mixed>|null $value */
    private function retryAfterSeconds(string|array|null $value): ?int
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : max(0, $timestamp - time());
    }
}
