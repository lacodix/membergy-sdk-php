<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Cache\CacheOptions;
use Lacodix\MembergySdk\Cache\ResponseCache;
use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\Exceptions\HttpRequestException;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\ShowPageRequest;
use Lacodix\MembergySdk\Tests\Support\ArrayCache;
use Lacodix\MembergySdk\Tests\Support\MutableCacheClock;
use Lacodix\MembergySdk\Tests\Support\StrictArrayCache;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

/**
 * @return array{
 *   client: MembergyClient,
 *   public: ArrayCache,
 *   member: ArrayCache,
 *   clock: MutableCacheClock
 * }
 */
function cachedClient(
    MockClient $mock,
    ?string $token = null,
    ?ArrayCache $public = null,
    ?ArrayCache $member = null,
    ?MutableCacheClock $clock = null,
): array {
    $public ??= new ArrayCache;
    $member ??= new ArrayCache;
    $clock ??= new MutableCacheClock;
    $cache = new ResponseCache(
        publicStore: $public,
        memberStore: $member,
        options: new CacheOptions(
            prefix: 'membergy-test',
            defaultTtl: 60,
            staleTtl: 300,
            resourceTtls: ['pages' => 60],
        ),
        clock: $clock,
    );
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'demo',
        userToken: $token,
        responseCache: $cache,
        tries: 3,
        retryInterval: 0,
    );
    $connector->withMockClient($mock);

    return [
        'client' => MembergyClient::fromConnector($connector),
        'public' => $public,
        'member' => $member,
        'clock' => $clock,
    ];
}

/** @return array<string, string> */
function publicCacheHeaders(string $etag = '"page-v1"'): array
{
    return [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'public, max-age=60, s-maxage=60, stale-while-revalidate=300, stale-if-error=300',
        'ETag' => $etag,
        'Last-Modified' => 'Tue, 11 Aug 2026 10:00:00 GMT',
        'Vary' => 'Accept, Authorization, Cookie',
        'X-Membergy-Contract-Version' => 'cms-v1',
    ];
}

it('serves a fresh public response without a second network request', function () {
    $mock = new MockClient([
        ShowPageRequest::class => MockResponse::make(
            contractFixture('pages/show.json'),
            200,
            publicCacheHeaders(),
        ),
    ]);
    ['client' => $client, 'public' => $public] = cachedClient($mock);

    $first = $client->content()->pages()->find('willkommen');
    $second = $client->content()->pages()->find('willkommen');

    expect($second->uuid)->toBe($first->uuid)
        ->and($public->writes)->toBe(1);
    $mock->assertSentCount(1);
});

it('revalidates stale entries with both validators and restores a 304 body', function (?string $token) {
    $conditional = [];
    $headers = publicCacheHeaders();
    if ($token !== null) {
        $headers['Cache-Control'] = 'private, max-age=60, stale-if-error=300';
    }
    $mock = new MockClient([
        MockResponse::make(contractFixture('pages/show.json'), 200, $headers),
        function (PendingRequest $pendingRequest) use (&$conditional): MockResponse {
            $conditional = [
                'etag' => $pendingRequest->headers()->get('If-None-Match'),
                'modified' => $pendingRequest->headers()->get('If-Modified-Since'),
            ];

            return MockResponse::make('', 304);
        },
    ]);
    ['client' => $client, 'clock' => $clock] = cachedClient($mock, $token);

    $first = $client->content()->pages()->find('willkommen');
    $clock->advance(61);
    $second = $client->content()->pages()->find('willkommen');

    expect($second->uuid)->toBe($first->uuid)
        ->and($conditional)->toBe([
            'etag' => '"page-v1"',
            'modified' => 'Tue, 11 Aug 2026 10:00:00 GMT',
        ]);
    $mock->assertSentCount(2);
})->with([null, 'synthetic-member-token']);

it('serves a still-usable stale response when revalidation returns a server error', function () {
    $mock = new MockClient([
        MockResponse::make(contractFixture('pages/show.json'), 200, publicCacheHeaders()),
        MockResponse::make(['message' => 'temporary'], 503, ['Content-Type' => 'application/json']),
    ]);
    ['client' => $client, 'clock' => $clock] = cachedClient($mock);

    $first = $client->content()->pages()->find('willkommen');
    $clock->advance(61);
    $second = $client->content()->pages()->find('willkommen');

    expect($second->uuid)->toBe($first->uuid);
    $mock->assertSentCount(2);
});

it('forgets stale content after an authoritative denial before a later server error', function (int $status, ?string $token) {
    $clock = new MutableCacheClock;
    $public = new ArrayCache;
    $member = new ArrayCache;
    $headers = publicCacheHeaders();
    if ($token !== null) {
        $headers['Cache-Control'] = 'private, max-age=60, stale-if-error=300';
    }
    $mock = new MockClient([
        MockResponse::make(['title' => 'old-content-marker'], 200, $headers),
        MockResponse::make(['title' => 'unrelated-content-marker'], 200, $headers),
        MockResponse::make(['message' => 'definitive denial'], $status),
        MockResponse::make(['message' => 'temporary outage'], 503),
        MockResponse::make(['message' => 'temporary outage'], 503),
    ]);
    $cache = new ResponseCache($public, $member, new CacheOptions(defaultTtl: 60, staleTtl: 300), $clock);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', $token, responseCache: $cache, tries: 1);
    $connector->withMockClient($mock);

    expect($connector->send(new ShowPageRequest('demo', 'revoked-page'))->status())->toBe(200);
    expect($connector->send(new ShowPageRequest('demo', 'unrelated-page'))->status())->toBe(200);
    $clock->advance(61);
    expect($connector->send(new ShowPageRequest('demo', 'revoked-page'))->status())->toBe($status);
    $final = $connector->send(new ShowPageRequest('demo', 'revoked-page'));

    expect($final->status())->toBe(503)
        ->and($final->isCached())->toBeFalse()
        ->and($final->body())->not->toContain('old-content-marker')
        ->and($final->getPendingRequest()->headers()->get('If-None-Match'))->toBeNull();
    $unrelated = $connector->send(new ShowPageRequest('demo', 'unrelated-page'));
    expect($unrelated->status())->toBe(200)
        ->and($unrelated->isCached())->toBeTrue()
        ->and($unrelated->body())->toContain('unrelated-content-marker');
    $mock->assertSentCount(5);
})->with([401, 403, 404, 410])->with([null, 'synthetic-member-token']);

it('evicts a denied stale response before exhausting the default three server-error attempts', function () {
    $clock = new MutableCacheClock;
    $payload = contractFixture('pages/show.json');
    $payload['data']['title'] = 'stale-denial-marker';
    $mock = new MockClient([
        MockResponse::make($payload, 200, publicCacheHeaders()),
        MockResponse::make(['message' => 'definitive denial'], 404),
        MockResponse::make(['message' => 'temporary outage'], 503),
        MockResponse::make(['message' => 'temporary outage'], 503),
        MockResponse::make(['message' => 'temporary outage'], 503),
    ]);
    $cache = new ResponseCache(new ArrayCache, options: new CacheOptions(defaultTtl: 60, staleTtl: 300), clock: $clock);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', responseCache: $cache, retryInterval: 0);
    $connector->withMockClient($mock);
    $client = MembergyClient::fromConnector($connector);

    expect($client->content()->pages()->find('willkommen')->title)->toBe('stale-denial-marker');
    $clock->advance(61);
    expect(fn () => $client->content()->pages()->find('willkommen'))->toThrow(ResourceNotFoundException::class);
    $mock->assertSentCount(2);
    expect(fn () => $client->content()->pages()->find('willkommen'))->toThrow(HttpRequestException::class);
    $mock->assertSentCount(5);
});

it('retains stale-if-error in each scope when no authoritative denial occurred', function (?string $token) {
    $headers = publicCacheHeaders();
    if ($token !== null) {
        $headers['Cache-Control'] = 'private, max-age=60, stale-if-error=300';
    }
    $mock = new MockClient([
        MockResponse::make(contractFixture('pages/show.json'), 200, $headers),
        MockResponse::make(['message' => 'temporary'], 503),
    ]);
    ['client' => $client, 'clock' => $clock] = cachedClient($mock, $token);

    $first = $client->content()->pages()->find('willkommen');
    $clock->advance(61);
    expect($client->content()->pages()->find('willkommen')->uuid)->toBe($first->uuid);
    $mock->assertSentCount(2);
})->with([null, 'synthetic-member-token']);

it('caches and invalidates with strict PSR-16 stores while preserving namespaces and host keys', function (?string $token) {
    $public = new StrictArrayCache;
    $member = new StrictArrayCache;
    $public->set('host_application_key', 'preserved');
    $member->set('host_application_key', 'preserved');
    $headers = publicCacheHeaders();
    if ($token !== null) {
        $headers['Cache-Control'] = 'private, max-age=60, stale-if-error=300';
    }
    $clients = [];
    $mocks = [];
    foreach (['website:a', 'website-a', str_repeat('long_namespace', 10)] as $index => $prefix) {
        $mock = new MockClient([
            MockResponse::make(['title' => 'namespace-'.$index], 200, $headers),
            MockResponse::make(['title' => 'after-resource-invalidation'], 200, $headers),
            MockResponse::make(['title' => 'after-flush'], 200, $headers),
        ]);
        $cache = new ResponseCache($public, $member, new CacheOptions(prefix: $prefix), new MutableCacheClock);
        $connector = new MembergyConnector('https://api.membergy.test', 'demo', $token, responseCache: $cache, tries: 1);
        $connector->withMockClient($mock);
        $client = MembergyClient::fromConnector($connector);
        // Direct sends let these synthetic payloads remain independent of DTO contracts.
        expect($connector->send(new ShowPageRequest('demo', 'sensitive-url-marker'))->json('title'))->toBe('namespace-'.$index)
            ->and($connector->send(new ShowPageRequest('demo', 'sensitive-url-marker'))->isCached())->toBeTrue();
        $mock->assertSentCount(1);
        $clients[] = [$client, $connector];
        $mocks[] = $mock;
    }

    [$client, $connector] = $clients[0];
    $client->flush('pages');
    expect($connector->send(new ShowPageRequest('demo', 'sensitive-url-marker'))->json('title'))->toBe('after-resource-invalidation');
    $client->flush();
    expect($connector->send(new ShowPageRequest('demo', 'sensitive-url-marker'))->json('title'))->toBe('after-flush')
        ->and($connector->send(new ShowPageRequest('demo', 'sensitive-url-marker'))->isCached())->toBeTrue();
    $mocks[0]->assertSentCount(3);

    foreach ([1, 2] as $index) {
        $otherConnector = $clients[$index][1];
        expect($otherConnector->send(new ShowPageRequest('demo', 'sensitive-url-marker'))->json('title'))->toBe('namespace-'.$index);
        $mocks[$index]->assertSentCount(1);
    }
    expect($public->get('host_application_key'))->toBe('preserved')
        ->and($member->get('host_application_key'))->toBe('preserved')
        ->and(implode('|', array_keys($public->inner->values)).implode('|', array_keys($member->inner->values)))
        ->not->toContain('sensitive-url-marker', 'synthetic-member-token', 'website:');
})->with([null, 'synthetic-member-token']);

it('invalidates one resource generation without clearing the host cache', function () {
    $updated = contractFixture('pages/show.json');
    $updated['data']['title'] = 'After purge';
    $mock = new MockClient([
        MockResponse::make(contractFixture('pages/show.json'), 200, publicCacheHeaders('"one"')),
        MockResponse::make($updated, 200, publicCacheHeaders('"two"')),
    ]);
    ['client' => $client, 'public' => $public] = cachedClient($mock);
    $public->set('host-application-key', 'preserved');

    expect($client->content()->pages()->find('willkommen')->title)->not->toBe('After purge');
    $client->flush('pages');
    expect($client->content()->pages()->find('willkommen')->title)->toBe('After purge')
        ->and($public->get('host-application-key'))->toBe('preserved');
    $mock->assertSentCount(2);
});

it('keeps member cache partitions outside the public store and never exposes raw tokens', function () {
    $memberPayload = contractFixture('pages/show.json');
    $memberPayload['data']['title'] = 'Member A';
    $otherPayload = contractFixture('pages/show.json');
    $otherPayload['data']['title'] = 'Member B';
    $headers = [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'private, max-age=60, stale-while-revalidate=300',
        'ETag' => '"member"',
    ];
    $public = new ArrayCache;
    $member = new ArrayCache;

    $mockA = new MockClient([
        MockResponse::make($memberPayload, 200, $headers),
    ]);
    ['client' => $clientA] = cachedClient($mockA, 'secret-token-a', $public, $member);
    expect($clientA->content()->pages()->find('willkommen')->title)->toBe('Member A')
        ->and($clientA->content()->pages()->find('willkommen')->title)->toBe('Member A');
    $mockA->assertSentCount(1);

    $mockB = new MockClient([
        MockResponse::make($otherPayload, 200, $headers),
    ]);
    ['client' => $clientB] = cachedClient($mockB, 'secret-token-b', $public, $member);
    expect($clientB->content()->pages()->find('willkommen')->title)->toBe('Member B')
        ->and(implode('|', array_keys($member->values)))->not->toContain('secret-token')
        ->and($public->values)->toBe([]);
    $mockB->assertSentCount(1);
});

it('obeys no-store for authenticated responses even when a private store exists', function () {
    $mock = new MockClient([
        MockResponse::make(
            contractFixture('pages/show.json'),
            200,
            ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store'],
        ),
        MockResponse::make(
            contractFixture('pages/show.json'),
            200,
            ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store'],
        ),
    ]);
    ['client' => $client, 'member' => $member] = cachedClient($mock, 'member-token');

    $client->content()->pages()->find('willkommen');
    $client->content()->pages()->find('willkommen');

    expect($member->values)->toBe([]);
    $mock->assertSentCount(2);
});

it('retries idempotent transient failures and maps an exhausted rate limit', function () {
    $retryMock = new MockClient([
        MockResponse::make(['message' => 'temporary'], 503),
        MockResponse::make(contractFixture('pages/show.json')),
    ]);
    ['client' => $retryClient] = cachedClient($retryMock);

    expect($retryClient->content()->pages()->find('willkommen')->slug)->toBe('willkommen');
    $retryMock->assertSentCount(2);

    $limitMock = new MockClient([
        MockResponse::make(['message' => 'limited'], 429, ['Retry-After' => '42']),
        MockResponse::make(['message' => 'limited'], 429, ['Retry-After' => '42']),
        MockResponse::make(['message' => 'limited'], 429, ['Retry-After' => '42']),
    ]);
    ['client' => $limitClient] = cachedClient($limitMock);

    try {
        $limitClient->content()->pages()->find('willkommen');
    } catch (RateLimitException $exception) {
        expect($exception->retryAfterSeconds)->toBe(42);
        $limitMock->assertSentCount(3);

        return;
    }

    throw new RuntimeException('Expected an exhausted rate limit exception.');
});

it('never retries a failed self-service write', function () {
    $mock = new MockClient([
        MockResponse::make(['message' => 'temporary'], 503),
    ]);
    ['client' => $client] = cachedClient($mock, 'member-token');

    expect(fn () => $client->me()->person()->update(['firstname' => 'Alex']))
        ->toThrow(HttpRequestException::class);
    $mock->assertSentCount(1);
});
