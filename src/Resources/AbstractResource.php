<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Resources;

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\PaginatedResult;
use Lacodix\MembergySdk\Exceptions\AuthenticationException;
use Lacodix\MembergySdk\Exceptions\HttpRequestException;
use Lacodix\MembergySdk\Exceptions\InvalidCredentialsException;
use Lacodix\MembergySdk\Exceptions\PasswordConfirmationRequiredException;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Exceptions\SelfServiceForbiddenException;
use Lacodix\MembergySdk\Exceptions\TransportException;
use Lacodix\MembergySdk\Exceptions\TwoFactorRequiredException;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Support\Payload;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Request;
use Saloon\Http\Response;
use SensitiveParameter;
use Throwable;

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
    protected function paginate(#[SensitiveParameter] Request $request, callable $hydrate): PaginatedResult
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

    protected function send(#[SensitiveParameter] Request $request): Response
    {
        try {
            return $this->connector->send($request)->throw();
        } catch (FatalRequestException) {
            throw new TransportException;
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            $status = $response->status();

            if ($status < 400 || $status >= 500) {
                throw new HttpRequestException($status);
            }

            if ($status === 429) {
                throw new RateLimitException(
                    retryAfterSeconds: $this->retryAfterSeconds($response->header('Retry-After')),
                );
            }

            $payload = $this->errorPayload($response);
            $code = $this->errorCode($payload);

            if ($status === 401) {
                if ($code === 'auth_invalid_credentials') {
                    throw new InvalidCredentialsException('The Membergy API rejected the supplied credentials.');
                }

                throw new AuthenticationException('The Membergy API rejected the supplied user token.');
            }

            if ($status === 422) {
                throw new RequestValidationException($this->validationErrors($payload), errorCode: $code);
            }

            if ($code === 'auth_two_factor_required' && is_array($payload)) {
                try {
                    $challenge = Data::nullableString($payload, 'challenge');
                    $expiresAt = Data::nullableDate($payload, 'expires_at');
                } catch (Throwable) {
                    throw new HttpRequestException($status);
                }

                throw new TwoFactorRequiredException(
                    'The Membergy account requires two-factor authentication.',
                    challenge: $challenge,
                    expiresAt: $expiresAt,
                );
            }

            if ($code === 'self_service_password_confirmation_required') {
                throw new PasswordConfirmationRequiredException('Please confirm the Membergy account password.');
            }

            if ($code === 'self_service_forbidden') {
                throw new SelfServiceForbiddenException('The Membergy API rejected the requested self-service operation.');
            }

            if ($code === 'self_service_not_found') {
                throw new ResourceNotFoundException('The requested Membergy self-service resource was not found.');
            }

            if ($status === 404) {
                throw new ResourceNotFoundException('The requested Membergy resource was not found.');
            }

            throw new HttpRequestException($status);
        }
    }

    private function errorPayload(#[SensitiveParameter] Response $response): mixed
    {
        try {
            return $response->json();
        } catch (Throwable) {
            return null;
        }
    }

    private function errorCode(#[SensitiveParameter] mixed $payload): ?string
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
    private function validationErrors(#[SensitiveParameter] mixed $payload): array
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
