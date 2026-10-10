<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\ManagementLogin;
use Lacodix\MembergySdk\Exceptions\AuthenticationException;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\SelfServiceForbiddenException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Me\IssueManagementLoginRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('issues the typed sensitive management login through the self-service contract', function () {
    $mock = new MockClient([IssueManagementLoginRequest::class => MockResponse::make(contractFixture('me/management-login.json'))]);
    $connector = new MembergyConnector(baseUrl: 'https://api.membergy.test', tenant: 'club', userToken: 'member-token');
    $connector->withMockClient($mock);
    $login = MembergyClient::fromConnector($connector)->me()->managementLogin();
    expect($login)->toBeInstanceOf(ManagementLogin::class);
    expect($login->url)->toBe('http://localhost/management-login/'.str_repeat('a', 64));
    expect($login->expiresAt->format(DATE_ATOM))->toBe('2026-10-09T12:01:00+00:00');
    ob_start();
    var_dump($login);
    expect(ob_get_clean())->not->toContain($login->url, str_repeat('a', 64))->toContain('[redacted]');
    $mock->assertSent(static fn ($request): bool => $request instanceof IssueManagementLoginRequest
        && $request->resolveEndpoint() === '/tenant/club/me/management-login'
        && $request->headers()->get('Accept') === 'application/vnd.membergy.self-service-v1+json');
});

it('maps management login failures through the existing self-service exceptions', function (int $status, string $fixture, string $exception) {
    $mock = new MockClient([IssueManagementLoginRequest::class => MockResponse::make(contractFixture($fixture), $status)]);
    $connector = new MembergyConnector(baseUrl: 'https://api.membergy.test', tenant: 'club', userToken: 'member-token');
    $connector->withMockClient($mock);
    expect(fn () => MembergyClient::fromConnector($connector)->me()->managementLogin())->toThrow($exception);
})->with([
    'forbidden' => [403, 'me/errors/forbidden.json', SelfServiceForbiddenException::class],
    'unauthenticated' => [401, 'me/errors/unauthenticated.json', AuthenticationException::class],
    'rate limited' => [429, 'me/errors/rate-limited.json', RateLimitException::class],
]);

it('marks management login URL and input payload arguments sensitive', function () {
    foreach ([[ManagementLogin::class, '__construct'], [ManagementLogin::class, 'fromArray']] as [$class, $method]) {
        expect((new ReflectionMethod($class, $method))->getParameters()[0]->getAttributes(SensitiveParameter::class))->toHaveCount(1);
    }
});

it('suppresses connector request and response debug callbacks for management tickets', function (bool $authenticated) {
    $mock = new MockClient([IssueManagementLoginRequest::class => MockResponse::make(contractFixture('me/management-login.json'))]);
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'club',
        userToken: $authenticated ? 'member-token' : null,
    );
    $connector->withMockClient($mock);
    $requestCalls = 0;
    $responseCalls = 0;
    $connector->debugRequest(static function () use (&$requestCalls): void {
        $requestCalls++;
    })->debugResponse(static function () use (&$responseCalls): void {
        $responseCalls++;
    });

    $login = MembergyClient::fromConnector($connector)->me()->managementLogin();

    expect($requestCalls)->toBe(0);
    expect($responseCalls)->toBe(0);
    expect($login)->toBeInstanceOf(ManagementLogin::class);
    $mock->assertSentCount(1);
})->with(['public connector' => false, 'authenticated connector' => true]);

it('suppresses direct request debug hooks and masks management login payloads', function (bool $callbacks) {
    $mock = new MockClient([IssueManagementLoginRequest::class => MockResponse::make(contractFixture('me/management-login.json'))]);
    $connector = new MembergyConnector(baseUrl: 'https://api.membergy.test', tenant: 'club', userToken: 'member-token');
    $connector->withMockClient($mock);
    $request = new IssueManagementLoginRequest('club');
    $requestCalls = 0;
    $responseCalls = 0;
    if ($callbacks) {
        $request->debugRequest(static function () use (&$requestCalls): void {
            $requestCalls++;
        })->debugResponse(static function () use (&$responseCalls): void {
            $responseCalls++;
        });
    } else {
        $request->debugRequest()->debugResponse();
    }

    ob_start();
    try {
        $response = $connector->send($request);
    } finally {
        $debugOutput = ob_get_clean();
    }

    expect($debugOutput === '')->toBeTrue();
    expect($requestCalls)->toBe(0);
    expect($responseCalls)->toBe(0);
    expect($request->__debugInfo() === [
        'endpoint' => '/tenant/club/me/management-login',
        'payload' => '[redacted]',
    ])->toBeTrue();
    expect($response->status())->toBe(200);
    $mock->assertSentCount(1);
})->with(['default hooks' => false, 'callback hooks' => true]);
