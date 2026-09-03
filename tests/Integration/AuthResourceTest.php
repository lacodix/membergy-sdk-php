<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\AccessToken;
use Lacodix\MembergySdk\DataObjects\AuthResult;
use Lacodix\MembergySdk\Exceptions\AuthenticationException;
use Lacodix\MembergySdk\Exceptions\InvalidCredentialsException;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\TwoFactorRequiredException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Auth\AbstractAuthRequest;
use Lacodix\MembergySdk\Requests\Auth\CreateTokenRequest;
use Lacodix\MembergySdk\Requests\Auth\RequestPasswordResetLinkRequest;
use Lacodix\MembergySdk\Requests\Auth\ResetPasswordRequest;
use Lacodix\MembergySdk\Requests\Auth\RevokeCurrentTokenRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

function authClient(MockClient $mock, ?string $token = null): MembergyClient
{
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'demo',
        userToken: $token,
    );
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

it('hydrates credentials tokens through the auth-v1 media type without leaking a stale bearer token', function () {
    $mock = new MockClient([
        CreateTokenRequest::class => MockResponse::make(contractFixture('auth/token.json')),
    ]);

    $token = authClient($mock, 'stale-token')->auth()->tokenFromCredentials(
        'member@example.test',
        'Secret-456-Ok',
        'Club website',
    );

    expect($token)->toBeInstanceOf(AccessToken::class)
        ->and($token->accessToken)->toBe('1|contract-token')
        ->and($token->tokenType)->toBe('Bearer')
        ->and($token->expiresAt)->toBeNull()
        ->and($token->emailVerified)->toBeTrue();

    $mock->assertSent(function (CreateTokenRequest $request, Response $response): bool {
        $pending = $response->getPendingRequest();

        return $request->resolveEndpoint() === '/token'
            && $request->body()->all() === [
                'email' => 'member@example.test',
                'password' => 'Secret-456-Ok',
                'device_name' => 'Club website',
            ]
            && $pending->headers()->get('Accept') === AbstractAuthRequest::MEDIA_TYPE
            && $pending->headers()->get('Authorization') === null;
    });
});

it('revokes the current bearer token and keeps the connector immutable', function () {
    $mock = new MockClient([
        RevokeCurrentTokenRequest::class => MockResponse::make(contractFixture('auth/revoked.json')),
    ]);
    $client = authClient($mock, 'member-token');

    $result = $client->auth()->revokeCurrentToken();

    expect($result)->toBeInstanceOf(AuthResult::class)
        ->and($result->status)->toBe('revoked')
        ->and($client->connector()->hasUserToken())->toBeTrue();

    $mock->assertSent(function (RevokeCurrentTokenRequest $request, Response $response): bool {
        $pending = $response->getPendingRequest();

        return $request->resolveEndpoint() === '/token'
            && $pending->headers()->get('Accept') === AbstractAuthRequest::MEDIA_TYPE
            && $pending->headers()->get('Authorization') === 'Bearer member-token';
    });
});

it('requests a reset link and submits the complete password reset body', function () {
    $mock = new MockClient([
        RequestPasswordResetLinkRequest::class => MockResponse::make(
            contractFixture('auth/reset-link-requested.json'),
        ),
        ResetPasswordRequest::class => MockResponse::make(contractFixture('auth/password-reset.json')),
    ]);
    $client = authClient($mock);

    $requested = $client->auth()->requestPasswordResetLink('member@example.test');
    $reset = $client->auth()->resetPassword(
        'reset-token',
        'member@example.test',
        'New-Password1#',
        'New-Password1#',
    );

    expect($requested->status)->toBe('reset_link_requested')
        ->and($reset->status)->toBe('password_reset');

    $mock->assertSent(fn (Request $request): bool => $request instanceof RequestPasswordResetLinkRequest
        && $request->resolveEndpoint() === '/auth/password/reset-link'
        && $request->body()->all() === ['email' => 'member@example.test']);
    $mock->assertSent(fn (Request $request): bool => $request instanceof ResetPasswordRequest
        && $request->resolveEndpoint() === '/auth/password/reset'
        && $request->body()->all() === [
            'token' => 'reset-token',
            'email' => 'member@example.test',
            'password' => 'New-Password1#',
            'password_confirmation' => 'New-Password1#',
        ]);
});

it('maps invalid credentials to the dedicated SDK exception', function () {
    $mock = new MockClient([
        CreateTokenRequest::class => MockResponse::make(
            contractFixture('auth/invalid-credentials.json'),
            401,
        ),
    ]);

    authClient($mock)->auth()->tokenFromCredentials(
        'member@example.test',
        'incorrect',
        'Club website',
    );
})->throws(InvalidCredentialsException::class);

it('maps confirmed two factor accounts to the dedicated SDK exception', function () {
    $mock = new MockClient([
        CreateTokenRequest::class => MockResponse::make(
            contractFixture('auth/two-factor-required.json'),
            409,
        ),
    ]);

    authClient($mock)->auth()->tokenFromCredentials(
        'member@example.test',
        'Secret-456-Ok',
        'Club website',
    );
})->throws(TwoFactorRequiredException::class);

it('maps the auth-v1 rate limit contract to the dedicated SDK exception', function () {
    $mock = new MockClient([
        CreateTokenRequest::class => MockResponse::make(
            contractFixture('auth/rate-limited.json'),
            429,
            ['Retry-After' => '42'],
        ),
    ]);

    try {
        authClient($mock)->auth()->tokenFromCredentials(
            'member@example.test',
            'Secret-456-Ok',
            'Club website',
        );
    } catch (RateLimitException $exception) {
        expect($exception->retryAfterSeconds)->toBe(42);

        return;
    }

    throw new RuntimeException('Expected an auth rate limit exception.');
});

it('passes auth-v1 validation errors through', function () {
    $validationMock = new MockClient([
        ResetPasswordRequest::class => MockResponse::make(contractFixture('auth/validation.json'), 422),
    ]);

    try {
        authClient($validationMock)->auth()->resetPassword(
            'invalid',
            'member@example.test',
            'New-Password1#',
            'New-Password1#',
        );
    } catch (RequestValidationException $exception) {
        expect($exception->errors)->toBe(['token' => ['Invalid password reset token.']]);

        return;
    }

    throw new RuntimeException('Expected an auth validation exception.');
});

it('maps revoked tokens to the stable authentication exception', function () {
    $unauthenticatedMock = new MockClient([
        RevokeCurrentTokenRequest::class => MockResponse::make(
            contractFixture('auth/unauthenticated.json'),
            401,
        ),
    ]);

    authClient($unauthenticatedMock, 'revoked-token')->auth()->revokeCurrentToken();
})->throws(AuthenticationException::class, 'The Membergy API rejected the supplied user token.');
