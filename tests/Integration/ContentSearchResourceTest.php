<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Cache\CacheOptions;
use Lacodix\MembergySdk\Cache\ResponseCache;
use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\EventSearchHit;
use Lacodix\MembergySdk\DataObjects\PageSearchHit;
use Lacodix\MembergySdk\DataObjects\PostSearchHit;
use Lacodix\MembergySdk\DataObjects\SearchGroup;
use Lacodix\MembergySdk\DataObjects\SearchHit;
use Lacodix\MembergySdk\DataObjects\SearchResult;
use Lacodix\MembergySdk\Enums\SearchResultType;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\SearchContentRequest;
use Lacodix\MembergySdk\Resources\SearchResource;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Tests\Support\ArrayCache;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

function contentSearchClient(
    MockClient $mock,
    ?string $token = null,
    ?ResponseCache $cache = null,
): MembergyClient {
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'demo',
        userToken: $token,
        responseCache: $cache,
        tries: 1,
        retryInterval: 0,
    );
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

/** @return array<string, string> */
function publicSearchCacheHeaders(string $etag): array
{
    return [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'public, max-age=60, s-maxage=60',
        'ETag' => $etag,
        'Vary' => 'Accept, Authorization, Cookie',
    ];
}

function searchHitTitle(SearchHit $hit): string
{
    return match (true) {
        $hit instanceof PageSearchHit => $hit->title,
        $hit instanceof PostSearchHit => $hit->title,
        $hit instanceof EventSearchHit => $hit->title,
        default => throw new RuntimeException('Unsupported search hit DTO.'),
    };
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, mixed>
 */
function searchPayloadWithFirstPageTitle(array $payload, string $title): array
{
    $groups = Data::objectList($payload, 'data');
    $items = Data::objectList($groups[0], 'items');
    $items[0]['title'] = $title;
    $groups[0]['items'] = $items;
    $payload['data'] = $groups;

    return $payload;
}

it('sends the exact search path and hydrates all discriminated result types', function () {
    $mock = new MockClient([
        SearchContentRequest::class => MockResponse::make(contractFixture('search/index.json')),
    ]);

    $result = contentSearchClient($mock, 'member-token')
        ->content()
        ->search()
        ->query("\u{200B}\u{FEFF}Überblick\t\u{3164}\n\u{1160}東京 #42\u{FEFF}\u{200B}")
        ->get();

    expect($result)->toBeInstanceOf(SearchResult::class)
        ->and($result->groups)->toHaveCount(3)
        ->and(array_map(
            static fn (SearchGroup $group): SearchResultType => $group->type,
            $result->groups,
        ))->toBe([
            SearchResultType::PAGE,
            SearchResultType::POST,
            SearchResultType::EVENT,
        ])
        ->and($result->group(SearchResultType::PAGE)?->items[0])
        ->toBeInstanceOf(PageSearchHit::class);

    $post = $result->group(SearchResultType::POST)?->items[0] ?? null;
    $event = $result->group(SearchResultType::EVENT)?->items[0] ?? null;

    expect($post)->toBeInstanceOf(PostSearchHit::class)
        ->and($event)->toBeInstanceOf(EventSearchHit::class);

    if (! $post instanceof PostSearchHit || ! $event instanceof EventSearchHit) {
        throw new RuntimeException('Expected discriminated search hit DTOs.');
    }

    expect($post->publishedAt->format(DATE_ATOM))->toBe('2026-08-08T10:00:00+00:00')
        ->and($event->startAt?->format(DATE_ATOM))->toBe('2026-09-15T19:00:00+02:00')
        ->and($event->endAt?->format(DATE_ATOM))->toBe('2026-09-15T21:00:00+02:00');

    $mock->assertSent(function (Request $request, Response $response): bool {
        return $request instanceof SearchContentRequest
            && $request->resolveEndpoint() === '/tenant/demo/content/search'
            && $request->query()->all() === ['q' => 'Überblick 東京 #42']
            && $response->getPendingRequest()->getUrl()
                === 'https://api.membergy.test/api/v1/tenant/demo/content/search'
            && $response->getPendingRequest()->headers()->get('Authorization')
                === 'Bearer member-token';
    });
});

it('requests one typed continuation group with bounded page metadata', function () {
    $groups = Data::objectList(contractFixture('search/index.json'), 'data');
    $postGroup = $groups[1];
    $postGroup['meta'] = [
        'page' => 2,
        'per_page' => 5,
        'has_more' => true,
        'next_page' => 3,
    ];
    $mock = new MockClient([
        SearchContentRequest::class => MockResponse::make(['data' => [$postGroup]]),
    ]);

    $result = contentSearchClient($mock)
        ->content()
        ->search()
        ->query('Jahresbericht')
        ->type(SearchResultType::POST)
        ->page(2)
        ->get();

    expect($result->groups)->toHaveCount(1)
        ->and($result->groups[0]->type)->toBe(SearchResultType::POST)
        ->and($result->groups[0]->items[0])->toBeInstanceOf(PostSearchHit::class)
        ->and($result->groups[0]->meta->page)->toBe(2)
        ->and($result->groups[0]->meta->perPage)->toBe(5)
        ->and($result->groups[0]->meta->hasMore)->toBeTrue()
        ->and($result->groups[0]->meta->nextPage)->toBe(3);

    $mock->assertSent(fn (Request $request): bool => $request instanceof SearchContentRequest
        && $request->query()->all() === [
            'q' => 'Jahresbericht',
            'type' => 'post',
            'page' => 2,
        ]);
});

it('rejects invalid client input before a request is sent', function () {
    $mock = new MockClient;
    $search = contentSearchClient($mock)->content()->search();

    expect($search->query('valid')->type(SearchResultType::PAGE)->page(1000))
        ->toBeInstanceOf(SearchResource::class);

    $invalidCalls = [
        static fn () => $search->get(),
        static fn () => $search->query('---'),
        static fn () => $search->query("\u{3164}\u{1160}"),
        static fn () => $search->query(str_repeat('a', 321)),
        static fn () => $search->query(implode(' ', range(1, 17))),
        static fn () => $search->query(implode("\u{3164}", range(1, 17))),
        static fn () => $search->query("\xC3\x28"),
        static fn () => $search->page(0),
        static fn () => $search->page(1001),
        static fn () => $search->query('valid')->page(2)->get(),
    ];

    foreach ($invalidCalls as $call) {
        expect($call)->toThrow(InvalidArgumentException::class);
    }

    $mock->assertSentCount(0);
});

it('maps search validation and rate limits through the shared exception contract', function () {
    $validationMock = new MockClient([
        SearchContentRequest::class => MockResponse::make(
            contractFixture('errors/validation.json'),
            422,
        ),
    ]);

    $validationWasMapped = false;
    try {
        contentSearchClient($validationMock)->content()->search()->query('valid')->get();
    } catch (RequestValidationException $exception) {
        expect($exception->errors)->toBe(['sort' => ['Sort ist unzulässig.']]);
        $validationWasMapped = true;
    }
    expect($validationWasMapped)->toBeTrue();

    $rateLimitMock = new MockClient([
        SearchContentRequest::class => MockResponse::make(
            contractFixture('errors/rate-limited.json'),
            429,
            ['Retry-After' => '42'],
        ),
    ]);

    try {
        contentSearchClient($rateLimitMock)->content()->search()->query('valid')->get();
    } catch (RateLimitException $exception) {
        expect($exception->retryAfterSeconds)->toBe(42);

        return;
    }

    throw new RuntimeException('Expected a typed rate-limit exception.');
});

it('rejects complete results for a typed search request', function () {
    $mock = new MockClient([
        SearchContentRequest::class => MockResponse::make(contractFixture('search/index.json')),
    ]);

    expect(fn () => contentSearchClient($mock)
        ->content()
        ->search()
        ->query('valid')
        ->type(SearchResultType::POST)
        ->get())
        ->toThrow(
            HydrationException::class,
            'typed search results must contain exactly one group',
        );
});

it('rejects a different single group for a typed search request', function () {
    $groups = Data::objectList(contractFixture('search/index.json'), 'data');
    $mock = new MockClient([
        SearchContentRequest::class => MockResponse::make(['data' => [$groups[0]]]),
    ]);

    expect(fn () => contentSearchClient($mock)
        ->content()
        ->search()
        ->query('valid')
        ->type(SearchResultType::POST)
        ->get())
        ->toThrow(
            HydrationException::class,
            "must match requested type 'post'",
        );
});

it('rejects a single group for an untyped search request', function () {
    $groups = Data::objectList(contractFixture('search/index.json'), 'data');
    $mock = new MockClient([
        SearchContentRequest::class => MockResponse::make(['data' => [$groups[1]]]),
    ]);

    expect(fn () => contentSearchClient($mock)
        ->content()
        ->search()
        ->query('valid')
        ->get())
        ->toThrow(
            HydrationException::class,
            'untyped search results must contain exactly three groups',
        );
});

it('rejects unknown discriminators and an invalid complete-group order', function () {
    $unknownGroups = Data::objectList(contractFixture('search/index.json'), 'data');
    $unknownGroups[0]['type'] = 'menu';
    $unknownMock = new MockClient([
        SearchContentRequest::class => MockResponse::make(['data' => [$unknownGroups[0]]]),
    ]);

    expect(fn () => contentSearchClient($unknownMock)
        ->content()
        ->search()
        ->query('valid')
        ->get())
        ->toThrow(HydrationException::class, 'unsupported search result type');

    $orderedGroups = Data::objectList(contractFixture('search/index.json'), 'data');
    $orderMock = new MockClient([
        SearchContentRequest::class => MockResponse::make([
            'data' => [$orderedGroups[1], $orderedGroups[0], $orderedGroups[2]],
        ]),
    ]);

    expect(fn () => contentSearchClient($orderMock)
        ->content()
        ->search()
        ->query('valid')
        ->get())
        ->toThrow(HydrationException::class, 'must be ordered page, post, event');
});

it('keys public search cache entries by query and isolates authenticated no-store responses', function () {
    $secondPublic = searchPayloadWithFirstPageTitle(contractFixture('search/index.json'), 'Second public query');

    $publicStore = new ArrayCache;
    $memberStore = new ArrayCache;
    $cache = new ResponseCache(
        publicStore: $publicStore,
        memberStore: $memberStore,
        options: new CacheOptions(
            prefix: 'membergy-search-test',
            defaultTtl: 0,
            resourceTtls: ['search' => 60],
        ),
    );
    $publicMock = new MockClient([
        MockResponse::make(
            contractFixture('search/index.json'),
            200,
            publicSearchCacheHeaders('"search-one"'),
        ),
        MockResponse::make(
            $secondPublic,
            200,
            publicSearchCacheHeaders('"search-two"'),
        ),
    ]);
    $publicClient = contentSearchClient($publicMock, cache: $cache);

    $first = $publicClient->content()->search()->query('first')->get();
    $cached = $publicClient->content()->search()->query('first')->get();
    $second = $publicClient->content()->search()->query('second')->get();

    expect(searchHitTitle($cached->groups[0]->items[0]))
        ->toBe(searchHitTitle($first->groups[0]->items[0]))
        ->and(searchHitTitle($second->groups[0]->items[0]))
        ->toBe('Second public query');
    $publicMock->assertSentCount(2);

    $memberOne = searchPayloadWithFirstPageTitle(contractFixture('search/index.json'), 'Member response one');
    $memberTwo = searchPayloadWithFirstPageTitle(contractFixture('search/index.json'), 'Member response two');
    $privateHeaders = [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'private, no-store',
    ];
    $memberMock = new MockClient([
        MockResponse::make($memberOne, 200, $privateHeaders),
        MockResponse::make($memberTwo, 200, $privateHeaders),
    ]);
    $memberClient = contentSearchClient($memberMock, 'secret-member-token', $cache);

    $firstMember = $memberClient->content()->search()->query('first')->get();
    $secondMember = $memberClient->content()->search()->query('first')->get();

    expect(searchHitTitle($firstMember->groups[0]->items[0]))->toBe('Member response one')
        ->and(searchHitTitle($secondMember->groups[0]->items[0]))->toBe('Member response two')
        ->and($memberStore->values)->toBe([])
        ->and(implode('|', array_keys($publicStore->values)))
        ->not->toContain('secret-member-token');
    $memberMock->assertSentCount(2);
});
