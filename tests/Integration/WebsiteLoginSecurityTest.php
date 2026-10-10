<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\AccessToken;
use Lacodix\MembergySdk\Exceptions\PasswordConfirmationRequiredException;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\TwoFactorRequiredException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Auth\CompleteTwoFactorRequest;
use Lacodix\MembergySdk\Requests\Auth\CreateTokenRequest;
use Lacodix\MembergySdk\Requests\Me\ConfirmPasswordRequest;
use Lacodix\MembergySdk\Requests\Me\ShowPersonFormRequest;
use Lacodix\MembergySdk\Resources\AuthResource;
use Lacodix\MembergySdk\Resources\PasswordResource;
use PHPUnit\Framework\Assert;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Response;

function hardenedLoginClient(MockClient $mock): MembergyClient
{
    $connector = new MembergyConnector('https://membergy.test', 'demo', 'old-website-token');
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

it('passes remember to credentials login and hydrates the absolute thirty-day expiry', function () {
    $mock = new MockClient([CreateTokenRequest::class => MockResponse::make(contractFixture('auth/token-remember.json'))]);
    $result = hardenedLoginClient($mock)->auth()->tokenFromCredentials('member@example.test', 'password', 'Website', remember: true);
    expect($result->expiresAt?->format('c'))->toBe('2026-11-07T12:00:00+00:00');
    $mock->assertSent(fn (CreateTokenRequest $request): bool => $request->body()->all()['remember'] === true);
});

it('exposes the second-step challenge and expiry while masking exception debugging', function () {
    $mock = new MockClient([CreateTokenRequest::class => MockResponse::make(contractFixture('auth/two-factor-required.json'), 409)]);
    try {
        hardenedLoginClient($mock)->auth()->tokenFromCredentials('member@example.test', 'SecretPassword1', 'Website');
        Assert::fail('Expected a second factor.');
    } catch (TwoFactorRequiredException $exception) {
        expect($exception->challenge)->toBe(str_repeat('a', 64))
            ->and($exception->expiresAt?->format('c'))->toBe('2026-10-08T12:05:00+00:00')
            ->and($exception->getPrevious())->toBeNull()
            ->and(json_encode($exception->__debugInfo()))->not->toContain(str_repeat('a', 64), 'SecretPassword1');
    }
});

it('completes either second factor without carrying an old bearer token or extra credential fields', function (array $factor) {
    $mock = new MockClient([CompleteTwoFactorRequest::class => MockResponse::make(contractFixture('auth/two-factor-token.json'))]);
    $result = hardenedLoginClient($mock)->auth()->completeTwoFactor('opaque-challenge', ...$factor);
    expect($result)->toBeInstanceOf(AccessToken::class)->and($result->accessToken)->toBe('1|contract-token');
    $mock->assertSent(function (CompleteTwoFactorRequest $request, Response $response) use ($factor): bool {
        return $request->body()->all() === ['challenge' => 'opaque-challenge', ...($factor['code'] ?? null
            ? ['code' => $factor['code']] : ['recovery_code' => $factor['recoveryCode']])]
            && $response->getPendingRequest()->headers()->get('Authorization') === null;
    });
})->with(['TOTP' => [['code' => '123456']], 'recovery code' => [['recoveryCode' => 'recovery-one']]]);

it('refuses two or no second factors before sending', function (?string $code, ?string $recovery) {
    expect(fn () => new CompleteTwoFactorRequest('opaque-challenge', $code, $recovery))->toThrow(InvalidArgumentException::class);
})->with([[null, null], ['123456', 'recovery-one']]);

it('preserves the stable challenge failure code in validation exceptions', function (string $fixture, string $code) {
    $mock = new MockClient([CompleteTwoFactorRequest::class => MockResponse::make(contractFixture($fixture), 422)]);
    try {
        hardenedLoginClient($mock)->auth()->completeTwoFactor('opaque-challenge', code: '123456');
        Assert::fail('Expected second-factor validation failure.');
    } catch (RequestValidationException $exception) {
        expect($exception->errorCode)->toBe($code)->and($exception->getPrevious())->toBeNull();
    }
})->with([
    ['auth/two-factor-invalid.json', 'auth_two_factor_invalid'],
    ['auth/two-factor-challenge-invalid.json', 'auth_two_factor_challenge_invalid'],
]);

it('confirms a password with the typed status and maps the stale-person error separately', function () {
    $mock = new MockClient([
        ConfirmPasswordRequest::class => MockResponse::make(contractFixture('me/password-confirmed.json')),
        ShowPersonFormRequest::class => MockResponse::make(contractFixture('me/errors/password-confirmation-required.json'), 423),
    ]);
    $client = hardenedLoginClient($mock);
    expect($client->me()->password()->confirm('SecretPassword1')->status)->toBe('password_confirmed');
    $mock->assertSent(fn (ConfirmPasswordRequest $request): bool => $request->resolveEndpoint() === '/tenant/demo/me/password/confirm'
        && $request->getMethod()->value === 'POST' && $request->body()->all() === ['password' => 'SecretPassword1']);
    expect(fn () => $client->me()->person()->form())->toThrow(PasswordConfirmationRequiredException::class);
});

it('maps confirmation rate limiting with Retry-After', function () {
    $mock = new MockClient([ConfirmPasswordRequest::class => MockResponse::make(contractFixture('me/errors/rate-limited.json'), 429, ['Retry-After' => '60'])]);
    try {
        hardenedLoginClient($mock)->me()->password()->confirm('SecretPassword1');
        Assert::fail('Expected rate limiting.');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfterSeconds)->toBe(60)->and($exception->getPrevious())->toBeNull();
    }
});

it('keeps credentials challenges codes confirmations and token values out of request response and object debugging', function () {
    $requests = [
        new CreateTokenRequest('member@example.test', 'SecretPassword1', 'Website'),
        new CompleteTwoFactorRequest('SecretChallengeValue', code: '123456'),
        new CompleteTwoFactorRequest('SecretChallengeValue', recoveryCode: 'SecretRecoveryValue'),
        new ConfirmPasswordRequest('demo', 'SecretPassword1'),
    ];
    foreach ($requests as $request) {
        $mock = new MockClient([$request::class => MockResponse::make(['data' => ['access_token' => 'SecretTokenValue']])]);
        $connector = new MembergyConnector('https://membergy.test', 'demo', 'SecretBearerValue');
        $connector->withMockClient($mock);
        $called = false;
        $debugger = function () use (&$called): void {
            $called = true;
        };
        $request->debugRequest($debugger)->debugResponse($debugger);
        $connector->debugRequest($debugger)->debugResponse($debugger)->send($request);
        expect($called)->toBeFalse()
            ->and(json_encode([$request->__debugInfo(), $connector->__debugInfo()]))
            ->not->toContain('SecretPassword1', 'SecretChallengeValue', '123456', 'SecretRecoveryValue', 'SecretBearerValue');
    }
    expect(json_encode((new AccessToken('SecretTokenValue', 'Bearer', null, true))->__debugInfo()))
        ->not->toContain('SecretTokenValue');
    foreach ([AuthResource::class => ['tokenFromCredentials', 'completeTwoFactor'], PasswordResource::class => ['confirm'],
        CreateTokenRequest::class => ['__construct'], CompleteTwoFactorRequest::class => ['__construct'],
        ConfirmPasswordRequest::class => ['__construct']] as $class => $methods) {
        foreach ($methods as $method) {
            foreach ((new ReflectionMethod($class, $method))->getParameters() as $parameter) {
                if (in_array($parameter->getName(), ['password', 'challenge', 'code', 'recoveryCode'], true)) {
                    expect($parameter->getAttributes(SensitiveParameter::class))->toHaveCount(1);
                }
            }
        }
    }
});
