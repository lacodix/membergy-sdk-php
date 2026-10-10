<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\Exceptions\AuthenticationException;
use Lacodix\MembergySdk\Exceptions\HttpRequestException;
use Lacodix\MembergySdk\Exceptions\InvalidCredentialsException;
use Lacodix\MembergySdk\Exceptions\MembergyException;
use Lacodix\MembergySdk\Exceptions\PasswordConfirmationRequiredException;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Exceptions\SelfServiceForbiddenException;
use Lacodix\MembergySdk\Exceptions\TransportException;
use Lacodix\MembergySdk\Exceptions\TwoFactorRequiredException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\ConfirmNewsletterSubscriptionRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPageRequest;
use Lacodix\MembergySdk\Requests\Content\UnsubscribeAllNewsletterRequest;
use Lacodix\MembergySdk\Resources\AbstractResource;
use Lacodix\MembergySdk\Resources\NewsletterResource;
use Lacodix\MembergySdk\Resources\PersonResource;
use PHPUnit\Framework\Assert;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

/** @return list<string> */
function resourceExceptionMarkers(): array
{
    return ['SYNTHETIC_BEARER_SECRET', 'SYNTHETIC_PASSWORD_SECRET', 'SYNTHETIC_IBAN_SECRET',
        'SYNTHETIC_NEWSLETTER_SECRET', 'SYNTHETIC_CHALLENGE_SECRET', 'SYNTHETIC_ERROR_CODE_SECRET',
        'SYNTHETIC_HOSTILE_BODY', 'https://hostile.example.test/private-secret'];
}

function safeExceptionClient(MockClient $mock): MembergyClient
{
    $connector = new MembergyConnector('https://sdk.membergy.test', 'contract-club', 'SYNTHETIC_BEARER_SECRET', retryInterval: 0);
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

function exerciseResourceFailure(#[SensitiveParameter] MembergyClient $client, string $operation): void
{
    match ($operation) {
        'read' => $client->content()->pages()->get(),
        'find' => $client->content()->pages()->find('missing-page'),
        'person read' => $client->me()->person()->get(),
        'person update' => $client->me()->person()->update(['bank_details' => ['iban' => 'SYNTHETIC_IBAN_SECRET']]),
        'credentials' => $client->auth()->tokenFromCredentials('member@example.test', 'SYNTHETIC_PASSWORD_SECRET', 'Website'),
        'revoke' => $client->auth()->revokeCurrentToken(),
        'password' => $client->me()->password()->confirm('SYNTHETIC_PASSWORD_SECRET'),
        'confirm' => $client->newsletter()->confirm('SYNTHETIC_NEWSLETTER_SECRET'),
        'unsubscribe' => $client->newsletter()->unsubscribeAll('SYNTHETIC_NEWSLETTER_SECRET'),
        default => throw new LogicException('Unknown test operation.'),
    };
}

function withResourceExceptionArguments(#[SensitiveParameter] callable $assertions): void
{
    $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
    $parameterLength = ini_set('zend.exception_string_param_max_len', '256');
    try {
        $assertions();
    } finally {
        ini_set('zend.exception_ignore_args', $ignoreArgs === false ? '1' : $ignoreArgs);
        ini_set('zend.exception_string_param_max_len', $parameterLength === false ? '0' : $parameterLength);
    }
}

function assertSafeResourceException(Throwable $exception): void
{
    expect($exception)->toBeInstanceOf(MembergyException::class)
        ->and($exception->getPrevious())->toBeNull()
        ->and(method_exists($exception, 'getResponse'))->toBeFalse()
        ->and(method_exists($exception, 'getPendingRequest'))->toBeFalse();
    ob_start();
    try {
        var_dump($exception);
        $dump = (string) ob_get_contents();
    } finally {
        ob_end_clean();
    }
    foreach (resourceExceptionMarkers() as $marker) {
        expect($exception->getMessage())->not->toContain($marker)
            ->and((string) $exception)->not->toContain($marker)
            ->and($dump)->not->toContain($marker);
    }
    expect($dump)->not->toContain('Saloon\\Http\\PendingRequest', 'Saloon\\Http\\Response', 'GuzzleHttp\\Exception');
    foreach ($exception->getTrace() as $frame) {
        if (($frame['class'] ?? null) === AbstractResource::class && in_array($frame['function'], ['send', 'paginate'], true)) {
            expect($frame['args'][0] ?? null)->toBeInstanceOf(SensitiveParameterValue::class);
        }
        if (($frame['class'] ?? null) === PersonResource::class && $frame['function'] === 'update'
            || ($frame['class'] ?? null) === NewsletterResource::class && in_array($frame['function'], ['confirm', 'unsubscribeAll', 'assertOpaqueToken'], true)) {
            expect($frame['args'][0] ?? null)->toBeInstanceOf(SensitiveParameterValue::class);
        }
    }
}

it('keeps mapped HTTP types and caller fields while removing raw vendor chains and dumps', function (
    int $status, string $fixture, string $operation, string $expected,
) {
    withResourceExceptionArguments(function () use ($status, $fixture, $operation, $expected): void {
        $body = contractFixture($fixture);
        $body['message'] = implode(' ', resourceExceptionMarkers());
        if ($status === 409) {
            $body['challenge'] = 'SYNTHETIC_CHALLENGE_SECRET';
        }
        if ($status === 422) {
            $body['errors'] = ['bank_details.iban' => ['SYNTHETIC_IBAN_SECRET']];
            $body['code'] = 'SYNTHETIC_ERROR_CODE_SECRET';
        }
        $mock = new MockClient([MockResponse::make($body, $status, ['Retry-After' => '42'])]);
        try {
            exerciseResourceFailure(safeExceptionClient($mock), $operation);
            Assert::fail('Expected a mapped SDK failure.');
        } catch (Throwable $exception) {
            expect($exception::class)->toBe($expected);
            assertSafeResourceException($exception);
            if ($exception instanceof RequestValidationException) {
                expect($exception->errors)->toBe($body['errors'])->and($exception->errorCode)->toBe($body['code']);
            }
            if ($exception instanceof TwoFactorRequiredException) {
                expect($exception->challenge)->toBe($body['challenge'])
                    ->and($exception->expiresAt?->format(DATE_ATOM))->toBe('2026-10-08T12:05:00+00:00');
            }
            if ($exception instanceof RateLimitException) {
                expect($exception->retryAfterSeconds)->toBe(42);
            }
        }
        $mock->assertSentCount(1);
    });
})->with([
    'invalid credentials' => [401, 'auth/invalid-credentials.json', 'credentials', InvalidCredentialsException::class],
    'invalid bearer' => [401, 'me/errors/unauthenticated.json', 'revoke', AuthenticationException::class],
    'forbidden' => [403, 'me/errors/forbidden.json', 'person read', SelfServiceForbiddenException::class],
    'own resource missing' => [404, 'me/errors/not-found.json', 'person read', ResourceNotFoundException::class],
    'public resource missing' => [404, 'errors/not-found.json', 'find', ResourceNotFoundException::class],
    'second factor' => [409, 'auth/two-factor-required.json', 'credentials', TwoFactorRequiredException::class],
    'password confirmation' => [423, 'me/errors/password-confirmation-required.json', 'person read', PasswordConfirmationRequiredException::class],
    'validation' => [422, 'errors/validation.json', 'person update', RequestValidationException::class],
    'rate limit' => [429, 'errors/rate-limited.json', 'confirm', RateLimitException::class],
]);

it('exposes only numeric HTTP status for unmapped errors and exhausted read or single write failures', function (
    int $status, string $operation, int $attempts,
) {
    withResourceExceptionArguments(function () use ($status, $operation, $attempts): void {
        $response = MockResponse::make(['message' => implode(' ', resourceExceptionMarkers())], $status);
        $mock = new MockClient(array_fill(0, $attempts, $response));
        try {
            exerciseResourceFailure(safeExceptionClient($mock), $operation);
            Assert::fail('Expected an HTTP SDK failure.');
        } catch (Throwable $exception) {
            if (! $exception instanceof HttpRequestException) {
                Assert::fail('Expected a status-only SDK HTTP exception.');
            }
            assertSafeResourceException($exception);
            expect($exception->status)->toBe($status)
                ->and($exception->getMessage())->toBe('The Membergy API request failed.');
            expect(array_keys(get_object_vars($exception)))->toBe(['status']);
        }
        $mock->assertSentCount($attempts);
    });
})->with([
    'unmapped public 418' => [418, 'read', 1],
    'unmapped auth 403' => [403, 'credentials', 1],
    'unmapped newsletter 409' => [409, 'confirm', 1],
    'expired newsletter 410' => [410, 'unsubscribe', 1],
    'server 500 GET' => [500, 'read', 3],
    'server 503 GET' => [503, 'read', 3],
    'server 503 auth POST' => [503, 'credentials', 1],
    'server 503 person PATCH' => [503, 'person update', 1],
    'server 503 password POST' => [503, 'password', 1],
    'server 503 confirmation POST' => [503, 'confirm', 1],
    'server 503 unsubscribe POST' => [503, 'unsubscribe', 1],
]);

it('sanitizes malformed and non-object HTTP error bodies without leaking parser failures', function (
    int $status, string $shape, string $expected,
) {
    withResourceExceptionArguments(function () use ($status, $shape, $expected): void {
        $body = match ($shape) {
            'HTML' => '<html>SYNTHETIC_HOSTILE_BODY</html>',
            'invalid JSON' => '{SYNTHETIC_HOSTILE_BODY',
            'null' => 'null',
            'scalar' => '"SYNTHETIC_HOSTILE_BODY"',
            'challenge shape' => '{"code":"auth_two_factor_required","challenge":[]}',
            'expiry' => '{"code":"auth_two_factor_required","expires_at":"SYNTHETIC_HOSTILE_BODY"}',
            default => throw new LogicException('Unknown test body shape.'),
        };
        $mock = new MockClient([MockResponse::make($body, $status, ['Retry-After' => '42'])]);
        try {
            exerciseResourceFailure(safeExceptionClient($mock), 'person update');
            Assert::fail('Expected a safe SDK failure for malformed metadata.');
        } catch (Throwable $exception) {
            expect($exception::class)->toBe($expected);
            assertSafeResourceException($exception);
            if ($exception instanceof RequestValidationException) {
                expect($exception->errors)->toBe([])->and($exception->errorCode)->toBeNull();
            }
            if ($exception instanceof HttpRequestException) {
                expect($exception->status)->toBe($status);
            }
        }
        $mock->assertSentCount(1);
    });
})->with([
    [401, 'HTML', AuthenticationException::class],
    [422, 'invalid JSON', RequestValidationException::class],
    [429, 'HTML', RateLimitException::class],
    [404, 'null', ResourceNotFoundException::class],
    [403, 'scalar', HttpRequestException::class],
    [409, 'challenge shape', HttpRequestException::class],
    [409, 'expiry', HttpRequestException::class],
]);

it('detaches transport failures including exhausted GET retries and never replays writes', function (string $operation, int $expectedAttempts) {
    withResourceExceptionArguments(function () use ($operation, $expectedAttempts): void {
        $attempts = 0;
        $failure = MockResponse::make()->throw(static function (PendingRequest $pending) use (&$attempts): FatalRequestException {
            $attempts++;

            return new FatalRequestException(new ConnectException(implode(' ', resourceExceptionMarkers()), $pending->createPsrRequest()), $pending);
        });
        $mock = new MockClient(array_fill(0, $expectedAttempts, $failure));
        try {
            exerciseResourceFailure(safeExceptionClient($mock), $operation);
            Assert::fail('Expected a transport SDK failure.');
        } catch (Throwable $exception) {
            expect($exception)->toBeInstanceOf(TransportException::class);
            assertSafeResourceException($exception);
            expect($exception->getMessage())->toBe('The Membergy API transport failed.')
                ->and(get_object_vars($exception))->toBe([]);
        }
        expect($attempts)->toBe($expectedAttempts);
        // Saloon throws the fake transport failure before recording any response.
        $mock->assertSentCount(0);
    });
})->with([['read', 3], ['credentials', 1], ['person update', 1], ['confirm', 1], ['unsubscribe', 1]]);

it('still recovers from a transient mocked transport failure on a GET', function () {
    $attempts = 0;
    $failure = MockResponse::make()->throw(static function (PendingRequest $pending) use (&$attempts): FatalRequestException {
        $attempts++;

        return new FatalRequestException(new ConnectException('Synthetic connection failure.', $pending->createPsrRequest()), $pending);
    });
    $mock = new MockClient([$failure, MockResponse::make(contractFixture('pages/show.json'))]);
    expect(safeExceptionClient($mock)->content()->pages()->find('willkommen')->slug)->toBe('willkommen')
        ->and($attempts)->toBe(1);
    $mock->assertSentCount(1);
});

it('marks request objects newsletter tokens and profile values sensitive in SDK trace frames', function () {
    foreach ([
        [AbstractResource::class, 'send', 'request'], [AbstractResource::class, 'paginate', 'request'],
        [AbstractResource::class, 'errorPayload', 'response'],
        [NewsletterResource::class, 'confirm', 'token'], [NewsletterResource::class, 'unsubscribeAll', 'token'],
        [NewsletterResource::class, 'assertOpaqueToken', 'token'],
        [ConfirmNewsletterSubscriptionRequest::class, '__construct', 'token'],
        [UnsubscribeAllNewsletterRequest::class, '__construct', 'token'],
        [PersonResource::class, 'update', 'values'],
        [RequestValidationException::class, '__construct', 'errors'], [RequestValidationException::class, '__construct', 'errorCode'],
    ] as [$class, $method, $name]) {
        $parameters = (new ReflectionMethod($class, $method))->getParameters();
        $parameter = array_values(array_filter($parameters, static fn (ReflectionParameter $parameter): bool => $parameter->getName() === $name))[0];
        expect($parameter->getAttributes(SensitiveParameter::class))->toHaveCount(1);
    }
});

it('masks opaque newsletter tokens even when local token validation stops before sending', function (string $operation) {
    withResourceExceptionArguments(function () use ($operation): void {
        $mock = new MockClient;
        $client = safeExceptionClient($mock);
        $token = 'SYNTHETIC_NEWSLETTER_SECRET'.str_repeat('x', 4097);
        try {
            $client->newsletter()->{$operation}($token);
            Assert::fail('Expected local token validation.');
        } catch (InvalidArgumentException $exception) {
            expect((string) $exception)->not->toContain('SYNTHETIC_NEWSLETTER_SECRET');
            foreach ($exception->getTrace() as $frame) {
                if (($frame['class'] ?? null) === NewsletterResource::class) {
                    expect($frame['args'][0] ?? null)->toBeInstanceOf(SensitiveParameterValue::class);
                }
            }
        }
        $mock->assertSentCount(0);
    });
})->with(['confirm', 'unsubscribeAll']);

it('leaves direct connector sends on the deliberately raw Saloon exception boundary', function () {
    $mock = new MockClient([MockResponse::make(['message' => 'SYNTHETIC_HOSTILE_BODY'], 418)]);
    try {
        safeExceptionClient($mock)->connector()->send(new ShowPageRequest('contract-club', 'example'));
        Assert::fail('Expected the low-level Saloon error.');
    } catch (RequestException $exception) {
        expect($exception->getStatus())->toBe(418)
            ->and($exception->getPendingRequest()->headers()->get('Authorization'))->toBe('Bearer SYNTHETIC_BEARER_SECRET');
    }
    $mock->assertSentCount(1);
});
