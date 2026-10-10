<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Cache\CacheOptions;
use Lacodix\MembergySdk\Cache\ResponseCache;
use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\NewsletterRequestResult;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\UnsubscribeAllNewsletterRequest;
use Lacodix\MembergySdk\Tests\Support\ArrayCache;
use Lacodix\MembergySdk\Tests\Support\MutableCacheClock;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

function newsletterUnsubscribeClient(MockClient $mock, ?string $userToken = null): MembergyClient
{
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'demo',
        userToken: $userToken,
    );
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

it('unsubscribes all categories with an opaque token through the public tenant endpoint', function () {
    $token = 'opaque.tenant-bound.unsubscribe-token';
    $mock = new MockClient([
        UnsubscribeAllNewsletterRequest::class => MockResponse::make(
            contractFixture('newsletter/unsubscribe-processed.json'),
        ),
    ]);

    $result = newsletterUnsubscribeClient($mock)->newsletter()->unsubscribeAll($token);

    expect($result)->toBeInstanceOf(NewsletterRequestResult::class)
        ->and($result->status)->toBe('unsubscribe_processed')
        ->and($result->extra)->toBe([]);

    $mock->assertSent(function (Request $request, Response $response) use ($token): bool {
        $pendingRequest = $response->getPendingRequest();

        return $request instanceof UnsubscribeAllNewsletterRequest
            && $request->resolveEndpoint() === '/tenant/demo/content/newsletter/unsubscribe/confirm'
            && $request->body()->all() === ['token' => $token]
            && ! str_contains((string) $pendingRequest->getUri(), $token)
            && $pendingRequest->headers()->get('Authorization') === null;
    });
});

it('accepts an opaque unsubscribe token at the 4096 byte boundary', function () {
    $token = str_repeat('a', 4096);
    $mock = new MockClient([
        UnsubscribeAllNewsletterRequest::class => MockResponse::make(
            contractFixture('newsletter/unsubscribe-processed.json'),
        ),
    ]);

    $result = newsletterUnsubscribeClient($mock)->newsletter()->unsubscribeAll($token);

    expect($result->status)->toBe('unsubscribe_processed');
    $mock->assertSent(fn (UnsubscribeAllNewsletterRequest $request): bool => $request->body()->all() === [
        'token' => $token,
    ]);
});

it('uses the explicitly selected guest connector without an authorization header', function () {
    $mock = new MockClient([
        UnsubscribeAllNewsletterRequest::class => MockResponse::make(
            contractFixture('newsletter/unsubscribe-processed.json'),
        ),
    ]);

    newsletterUnsubscribeClient($mock, 'member-token')
        ->withoutUserToken()
        ->newsletter()
        ->unsubscribeAll('opaque-token');

    $mock->assertSent(function (UnsubscribeAllNewsletterRequest $request, Response $response): bool {
        return $response->getPendingRequest()->headers()->get('Authorization') === null;
    });
});

it('rejects invalid unsubscribe tokens before sending a request', function (string $token) {
    $mock = new MockClient;

    expect(fn () => newsletterUnsubscribeClient($mock)->newsletter()->unsubscribeAll($token))
        ->toThrow(InvalidArgumentException::class, 'Invalid newsletter unsubscribe token.');
    $mock->assertSentCount(0);
})->with([
    'empty' => [''],
    'leading whitespace' => [' opaque-token'],
    'trailing whitespace' => ["opaque-token\n"],
    'unicode outer whitespace' => ["\u{00A0}opaque-token"],
    'invalid UTF-8' => ["\xC3\x28"],
    'more than 4096 bytes' => [str_repeat('a', 4097)],
]);

it('maps unsubscribe validation errors through the shared SDK exception', function () {
    $mock = new MockClient([
        UnsubscribeAllNewsletterRequest::class => MockResponse::make([
            'message' => 'The request parameters are invalid.',
            'code' => 'cms_validation_failed',
            'errors' => [
                'token' => ['The token field is invalid.'],
            ],
        ], 422),
    ]);

    try {
        newsletterUnsubscribeClient($mock)->newsletter()->unsubscribeAll('opaque-token');
    } catch (RequestValidationException $exception) {
        expect($exception->errors)->toBe([
            'token' => ['The token field is invalid.'],
        ]);

        return;
    }

    throw new RuntimeException('Expected a newsletter unsubscribe validation exception.');
});

it('maps an unsubscribe rate limit without retrying the write', function () {
    $mock = new MockClient([
        UnsubscribeAllNewsletterRequest::class => MockResponse::make(
            contractFixture('errors/rate-limited.json'),
            429,
            ['Retry-After' => '42'],
        ),
    ]);

    try {
        newsletterUnsubscribeClient($mock)->newsletter()->unsubscribeAll('opaque-token');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfterSeconds)->toBe(42);
        $mock->assertSentCount(1);

        return;
    }

    throw new RuntimeException('Expected a newsletter unsubscribe rate limit exception.');
});

it('never writes an unsubscribe token or response into the response cache', function () {
    $token = 'sensitive-opaque-unsubscribe-token';
    $publicStore = new ArrayCache;
    $memberStore = new ArrayCache;
    $options = new CacheOptions(prefix: 'membergy-test');
    $responseCache = new ResponseCache(
        publicStore: $publicStore,
        memberStore: $memberStore,
        options: $options,
        clock: new MutableCacheClock,
    );
    $mock = new MockClient([
        UnsubscribeAllNewsletterRequest::class => MockResponse::make(
            contractFixture('newsletter/unsubscribe-processed.json'),
            200,
            ['Cache-Control' => 'private, no-store'],
        ),
    ]);
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'demo',
        responseCache: $responseCache,
    );
    $connector->withMockClient($mock);

    MembergyClient::fromConnector($connector)->newsletter()->unsubscribeAll($token);

    $cacheSnapshot = serialize([$publicStore->values, $memberStore->values]);
    $generationKey = hash('sha256', $options->prefix.'|generation|resource:newsletter');

    expect(array_keys($publicStore->values))->toBe([$generationKey])
        ->and(array_keys($memberStore->values))->toBe([$generationKey])
        ->and(str_contains($cacheSnapshot, $token))->toBeFalse()
        ->and(str_contains($cacheSnapshot, 'unsubscribe_processed'))->toBeFalse();
});
