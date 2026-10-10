<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Cache\CacheOptions;
use Lacodix\MembergySdk\Cache\ResponseCache;
use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\NewsletterRequestResult;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\ConfirmNewsletterSubscriptionRequest;
use Lacodix\MembergySdk\Tests\Support\ArrayCache;
use Lacodix\MembergySdk\Tests\Support\MutableCacheClock;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

function newsletterConfirmationClient(MockClient $mock, ?string $userToken = null): MembergyClient
{
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'demo',
        userToken: $userToken,
    );
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

it('confirms an opaque newsletter token through the public tenant endpoint', function () {
    $token = 'opaque.tenant-bound.confirmation-token';
    $mock = new MockClient([
        ConfirmNewsletterSubscriptionRequest::class => MockResponse::make(
            contractFixture('newsletter/confirmation-processed.json'),
        ),
    ]);

    $result = newsletterConfirmationClient($mock)->newsletter()->confirm($token);

    expect($result)->toBeInstanceOf(NewsletterRequestResult::class)
        ->and($result->status)->toBe('confirmation_processed')
        ->and($result->extra)->toBe([]);

    $mock->assertSent(function (Request $request, Response $response) use ($token): bool {
        $pendingRequest = $response->getPendingRequest();

        return $request instanceof ConfirmNewsletterSubscriptionRequest
            && $request->resolveEndpoint() === '/tenant/demo/content/newsletter/confirm'
            && $request->body()->all() === ['token' => $token]
            && ! str_contains((string) $pendingRequest->getUri(), $token)
            && $pendingRequest->headers()->get('Authorization') === null;
    });
});
it('accepts an opaque confirmation token at the 4096 byte boundary', function () {
    $token = str_repeat('a', 4096);
    $mock = new MockClient([
        ConfirmNewsletterSubscriptionRequest::class => MockResponse::make(
            contractFixture('newsletter/confirmation-processed.json'),
        ),
    ]);

    $result = newsletterConfirmationClient($mock)->newsletter()->confirm($token);

    expect($result->status)->toBe('confirmation_processed');
    $mock->assertSent(fn (ConfirmNewsletterSubscriptionRequest $request): bool => $request->body()->all() === [
        'token' => $token,
    ]);
});

it('keeps the connectors configured bearer behavior for newsletter confirmation', function () {
    $mock = new MockClient([
        ConfirmNewsletterSubscriptionRequest::class => MockResponse::make(
            contractFixture('newsletter/confirmation-processed.json'),
        ),
    ]);

    newsletterConfirmationClient($mock, 'member-token')->newsletter()->confirm('opaque-token');

    $mock->assertSent(function (ConfirmNewsletterSubscriptionRequest $request, Response $response): bool {
        return $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer member-token';
    });
});

it('rejects invalid confirmation tokens before sending a request', function (string $token) {
    $mock = new MockClient;

    expect(fn () => newsletterConfirmationClient($mock)->newsletter()->confirm($token))
        ->toThrow(InvalidArgumentException::class, 'Invalid newsletter confirmation token.');
    $mock->assertSentCount(0);
})->with([
    'empty' => [''],
    'leading whitespace' => [' opaque-token'],
    'trailing whitespace' => ["opaque-token\n"],
    'unicode outer whitespace' => ["\u{00A0}opaque-token"],
    'invalid UTF-8' => ["\xC3\x28"],
    'more than 4096 bytes' => [str_repeat('a', 4097)],
]);

it('maps confirmation validation errors through the shared SDK exception', function () {
    $mock = new MockClient([
        ConfirmNewsletterSubscriptionRequest::class => MockResponse::make([
            'message' => 'The request parameters are invalid.',
            'code' => 'cms_validation_failed',
            'errors' => [
                'token' => ['The token field is invalid.'],
            ],
        ], 422),
    ]);

    try {
        newsletterConfirmationClient($mock)->newsletter()->confirm('opaque-token');
    } catch (RequestValidationException $exception) {
        expect($exception->errors)->toBe([
            'token' => ['The token field is invalid.'],
        ]);

        return;
    }

    throw new RuntimeException('Expected a newsletter confirmation validation exception.');
});

it('maps a confirmation rate limit without retrying the write', function () {
    $mock = new MockClient([
        ConfirmNewsletterSubscriptionRequest::class => MockResponse::make(
            contractFixture('errors/rate-limited.json'),
            429,
            ['Retry-After' => '42'],
        ),
    ]);

    try {
        newsletterConfirmationClient($mock)->newsletter()->confirm('opaque-token');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfterSeconds)->toBe(42);
        $mock->assertSentCount(1);

        return;
    }

    throw new RuntimeException('Expected a newsletter confirmation rate limit exception.');
});

it('never writes a confirmation token or response into the response cache', function () {
    $token = 'sensitive-opaque-confirmation-token';
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
        ConfirmNewsletterSubscriptionRequest::class => MockResponse::make(
            contractFixture('newsletter/confirmation-processed.json'),
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

    MembergyClient::fromConnector($connector)->newsletter()->confirm($token);

    $cacheSnapshot = serialize([$publicStore->values, $memberStore->values]);
    $generationKey = hash('sha256', $options->prefix.'|generation|resource:newsletter');

    expect(array_keys($publicStore->values))->toBe([$generationKey])
        ->and(array_keys($memberStore->values))->toBe([$generationKey])
        ->and(str_contains($cacheSnapshot, $token))->toBeFalse()
        ->and(str_contains($cacheSnapshot, 'confirmation_processed'))->toBeFalse();
});
