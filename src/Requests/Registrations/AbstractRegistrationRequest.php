<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Requests\Registrations;

use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationResume;
use Lacodix\MembergySdk\Exceptions\RegistrationException;
use Lacodix\MembergySdk\Exceptions\RegistrationIdentityException;
use Lacodix\MembergySdk\Requests\RedactsSensitivePayload;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;
use SensitiveParameter;
use Throwable;

abstract class AbstractRegistrationRequest extends Request
{
    use RedactsSensitivePayload;

    public const RESUME_HEADER = 'X-Membergy-Registration-Resume';

    public const MEDIA_TYPE = 'application/vnd.membergy.registration-v1+json';

    // Registration transport never automatically replays a request, including a token exchange.
    public ?int $tries = 1;

    protected bool $requiresGuest = false;

    public function __construct(#[SensitiveParameter] private readonly ?RegistrationResume $resume = null) {}

    /** @return array<string, mixed> */
    protected function defaultHeaders(): array
    {
        return $this->resume === null
            ? ['Accept' => 'application/json']
            : ['Accept' => 'application/json', self::RESUME_HEADER => $this->resume->headerValue()];
    }

    public function boot(#[SensitiveParameter] PendingRequest $pendingRequest): void
    {
        // A context belongs to exactly one request. Never accept one on a shared connector.
        foreach ($pendingRequest->getConnector()->headers()->all() as $name => $value) {
            if (strtolower((string) $name) === strtolower(self::RESUME_HEADER)) {
                throw new RegistrationIdentityException('Supply a registration resume context per request only.');
            }
        }
        if ($pendingRequest->query()->all() !== []) {
            throw new RegistrationIdentityException('Registration requests do not accept query parameters.');
        }
        $auth = false;
        $contexts = [];
        foreach ($pendingRequest->headers()->all() as $name => $value) {
            $name = strtolower((string) $name);
            if ($name === 'authorization') {
                $auth = true;
            }
            if ($name === strtolower(self::RESUME_HEADER)) {
                $contexts[] = $value;
            }
        }
        $expected = $this->resume === null ? [] : [$this->resume->headerValue()];
        if ($contexts !== $expected || ($auth && ($this->resume !== null || $this->requiresGuest))) {
            throw new RegistrationIdentityException('User authentication and registration resume credentials cannot be combined.');
        }
        // Credentials and document bytes must not follow redirects to another endpoint or host.
        $pendingRequest->config()->add('allow_redirects', false);
    }

    public function getRequestException(#[SensitiveParameter] Response $response, #[SensitiveParameter] ?Throwable $senderException): ?Throwable
    {
        return RegistrationException::fromResponse($response);
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return ['type' => static::class];
    }
}
