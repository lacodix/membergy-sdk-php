<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\PostSummary;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\ListPostsRequest;
use Lacodix\MembergySdk\Resources\PostsResource;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

function postsCursorResource(MockClient $mock): PostsResource
{
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: testCmsToken());
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector)->content()->posts();
}

/** @return array<string, mixed> */
function postsCursorPayload(?string $next, ?string $previous, bool $empty = false): array
{
    $path = 'https://api.membergy.test/api/v1/tenant/demo/content/posts';
    $url = static fn (string $cursor): string => $path.'?'.http_build_query([
        'pagination' => 'cursor',
        'category' => 'aktuelles',
        'per_page' => 10,
        'cursor' => $cursor,
    ]);

    return [
        'data' => $empty ? [] : contractFixture('posts/index.json')['data'],
        'meta' => [
            'per_page' => 10,
            'path' => $path,
            'next_cursor' => $next,
            'prev_cursor' => $previous,
        ],
        'links' => [
            'first' => null,
            'last' => null,
            'prev' => $previous === null ? null : $url($previous),
            'next' => $next === null ? null : $url($next),
        ],
    ];
}

it('explicitly selects the initial posts cursor page with an omitted or null cursor', function (bool $explicitNull) {
    $payload = postsCursorPayload('opaque-next', null);
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make($payload)]);
    $posts = postsCursorResource($mock)->category('aktuelles');

    $result = ($explicitNull ? $posts->cursor(null) : $posts->cursor())->perPage(10)->get();

    expect($result->items[0])->toBeInstanceOf(PostSummary::class)
        ->and($result->items[0]->slug)->toBe('erfolg-beim-wertungsspiel')
        ->and($result->items[0]->category?->slug)->toBe('aktuelles')
        ->and($result->items[0]->publishedAt?->format(DATE_ATOM))->toBe('2026-08-08T10:00:00+00:00')
        ->and($result->meta)->toBe($payload['meta'])
        ->and($result->links)->toBe($payload['links'])
        ->and($result->perPage())->toBe(10)
        ->and($result->currentPage())->toBeNull()
        ->and($result->lastPage())->toBeNull()
        ->and($result->total())->toBeNull()
        ->and($result->nextCursor())->toBe('opaque-next')
        ->and($result->previousCursor())->toBeNull()
        ->and($result->hasMorePages())->toBeTrue();

    $mock->assertSent(fn (Request $request, Response $response): bool => $request instanceof ListPostsRequest
        && $request->getMethod() === Method::GET
        && $request->resolveEndpoint() === '/tenant/demo/content/posts'
        && $response->getPendingRequest()->getUrl() === 'https://api.membergy.test/api/v1/tenant/demo/content/posts'
        && $response->getPendingRequest()->getUri()->getQuery() === 'category=aktuelles&pagination=cursor&per_page=10'
        && $request->query()->all() === ['category' => 'aktuelles', 'pagination' => 'cursor', 'per_page' => 10]);
    $mock->assertSentCount(1);
})->with([false, true]);

it('passes opaque next and previous cursors unchanged and preserves cursor metadata and links', function () {
    $next = 'eyJpZCI6NDIsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0';
    $previous = 'eyJpZCI6NDIsIl9wb2ludHNUb05leHRJdGVtcyI6ZmFsc2V9';
    $firstPayload = postsCursorPayload($next, null);
    $lastPayload = postsCursorPayload(null, $previous);
    $mock = new MockClient([
        MockResponse::make($firstPayload),
        MockResponse::make($lastPayload),
        MockResponse::make($firstPayload),
    ]);
    $posts = postsCursorResource($mock)->cursor()->category('aktuelles')->perPage(10);

    $first = $posts->get();
    $last = $posts->cursor($first->nextCursor())->get();
    $back = $posts->cursor($last->previousCursor())->get();

    expect($last->meta)->toBe($lastPayload['meta'])
        ->and($last->links)->toBe($lastPayload['links'])
        ->and($last->nextCursor())->toBeNull()
        ->and($last->previousCursor())->toBe($previous)
        ->and($last->hasMorePages())->toBeFalse()
        ->and($last->items[0])->toBeInstanceOf(PostSummary::class)
        ->and($back->meta)->toBe($first->meta)
        ->and($back->links)->toBe($first->links)
        ->and($back->items[0]->uuid)->toBe($first->items[0]->uuid);

    foreach ([$next, $previous] as $cursor) {
        $mock->assertSent(fn (Request $request, Response $response): bool => $request instanceof ListPostsRequest
            && $response->getPendingRequest()->getUri()->getQuery() === 'pagination=cursor&category=aktuelles&per_page=10&cursor='.$cursor
            && $request->query()->all() === [
                'pagination' => 'cursor', 'category' => 'aktuelles', 'per_page' => 10, 'cursor' => $cursor,
            ]);
    }
    $mock->assertSentCount(3);
});

it('hydrates an exhausted empty posts cursor response', function () {
    $payload = postsCursorPayload(null, null, empty: true);
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make($payload)]);

    $result = postsCursorResource($mock)->cursor()->get();

    expect($result->items)->toBe([])
        ->and($result->meta)->toBe($payload['meta'])
        ->and($result->links)->toBe($payload['links'])
        ->and($result->nextCursor())->toBeNull()
        ->and($result->previousCursor())->toBeNull()
        ->and($result->hasMorePages())->toBeFalse();
});

it('rejects blank non-null posts cursors before sending a request', function (string $cursor) {
    $mock = new MockClient;
    $posts = postsCursorResource($mock)->page(3);

    expect(fn () => $posts->cursor($cursor))->toThrow(InvalidArgumentException::class, 'Cursor must not be empty.');
    $mock->assertSentCount(0);
})->with(['', ' ', "\t\n\r"]);

it('keeps posts resource chains immutable while switching pagination modes and resetting cursors', function () {
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make(contractFixture('posts/index.json'))]);
    $base = postsCursorResource($mock)->category('aktuelles')->perPage(20);
    $offset = $base->page(4);
    $cursor = $offset->cursor('opaque-next');
    $reset = $cursor->cursor(null);
    $newCategory = $cursor->category('berichte')->perPage(5);
    $backToOffset = $cursor->page(2);

    expect($offset)->not->toBe($base)
        ->and($cursor)->not->toBe($offset)
        ->and($reset)->not->toBe($cursor)
        ->and($newCategory)->not->toBe($cursor)
        ->and($backToOffset)->not->toBe($cursor);

    foreach ([$base, $offset, $cursor, $reset, $newCategory, $backToOffset] as $resource) {
        $resource->get();
    }

    foreach ([
        ['category' => 'aktuelles', 'per_page' => 20],
        ['category' => 'aktuelles', 'per_page' => 20, 'page' => 4],
        ['category' => 'aktuelles', 'per_page' => 20, 'pagination' => 'cursor', 'cursor' => 'opaque-next'],
        ['category' => 'aktuelles', 'per_page' => 20, 'pagination' => 'cursor'],
        ['category' => 'berichte', 'per_page' => 5, 'pagination' => 'cursor', 'cursor' => 'opaque-next'],
        ['category' => 'aktuelles', 'per_page' => 20, 'page' => 2],
    ] as $query) {
        $mock->assertSent(fn (Request $request): bool => $request instanceof ListPostsRequest && $request->query()->all() === $query);
    }
    $mock->assertSentCount(6);
});

it('preserves default and explicit offset posts requests and metadata', function (bool $explicitPage) {
    $payload = contractFixture('posts/index.json');
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make($payload)]);
    $posts = postsCursorResource($mock);
    $query = [];
    if ($explicitPage) {
        $posts = $posts->page(1)->category('aktuelles')->perPage(20);
        $query = ['page' => 1, 'category' => 'aktuelles', 'per_page' => 20];
    }

    $result = $posts->get();

    expect($result->meta)->toBe($payload['meta'])
        ->and($result->links)->toBe($payload['links'])
        ->and($result->items[0])->toBeInstanceOf(PostSummary::class)
        ->and($result->currentPage())->toBe(1)
        ->and($result->lastPage())->toBe(1)
        ->and($result->total())->toBe(1)
        ->and($result->perPage())->toBe(20)
        ->and($result->nextCursor())->toBeNull()
        ->and($result->previousCursor())->toBeNull()
        ->and($result->hasMorePages())->toBeFalse();
    $mock->assertSent(fn (Request $request): bool => $request instanceof ListPostsRequest && $request->query()->all() === $query);
    $mock->assertSentCount(1);
})->with([false, true]);

it('retains posts pagination bounds in cursor mode', function () {
    $mock = new MockClient;
    $posts = postsCursorResource($mock)->cursor();

    foreach ([0, 101] as $perPage) {
        expect(fn () => $posts->perPage($perPage))->toThrow(InvalidArgumentException::class);
    }
    expect(fn () => $posts->page(0))->toThrow(InvalidArgumentException::class);
    $mock->assertSentCount(0);
});

it('maps backend cursor validation errors through the existing SDK exception', function () {
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make([
        'message' => 'Invalid cursor.',
        'errors' => ['cursor' => ['The cursor is invalid.']],
    ], 422)]);

    try {
        postsCursorResource($mock)->cursor('malformed-but-nonblank')->get();
    } catch (RequestValidationException $exception) {
        expect($exception->errors)->toBe(['cursor' => ['The cursor is invalid.']]);
        $mock->assertSentCount(1);

        return;
    }

    throw new RuntimeException('Expected a typed cursor validation exception.');
});

it('sends category lists unchanged in one posts request without filtering the response locally', function () {
    $payload = postsCursorPayload(null, null);
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make($payload)]);

    $result = postsCursorResource($mock)->categories('News / local', 'Été', 'News / local')
        ->excludeCategories('Été', 'Other')->cursor('opaque+cursor=')->perPage(10)->get();

    expect($result->items)->toHaveCount(1)
        ->and($result->items[0]->slug)->toBe($payload['data'][0]['slug']);
    $query = [
        'categories' => ['News / local', 'Été', 'News / local'],
        'exclude_categories' => ['Été', 'Other'],
        'pagination' => 'cursor', 'cursor' => 'opaque+cursor=', 'per_page' => 10,
    ];
    $mock->assertSent(fn (Request $request, Response $response): bool => $request instanceof ListPostsRequest
        && $request->getMethod() === Method::GET
        && $request->resolveEndpoint() === '/tenant/demo/content/posts'
        && $request->query()->all() === $query
        && $response->getPendingRequest()->getUri()->getQuery() === 'categories%5B0%5D=News+%2F+local&categories%5B1%5D=%C3%89t%C3%A9&categories%5B2%5D=News+%2F+local&exclude_categories%5B0%5D=%C3%89t%C3%A9&exclude_categories%5B1%5D=Other&pagination=cursor&cursor=opaque%2Bcursor%3D&per_page=10');
    $mock->assertSentCount(1);
});

it('sends exclusions alone and retains uncategorized posts returned by the backend', function () {
    $payload = contractFixture('posts/index.json');
    $payload['data'][0]['category'] = null;
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make($payload)]);

    $result = postsCursorResource($mock)->excludeCategories('news')->get();

    expect($result->items)->toHaveCount(1)->and($result->items[0]->category)->toBeNull();
    $mock->assertSent(fn (Request $request): bool => $request instanceof ListPostsRequest
        && $request->query()->all() === ['exclude_categories' => ['news']]);
    $mock->assertSentCount(1);
});

it('combines legacy single category inclusion with exclusions including overlap', function () {
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make(contractFixture('posts/index.json'))]);

    postsCursorResource($mock)->category('news')->excludeCategories('news', 'archive')->page(2)->get();

    $mock->assertSent(fn (Request $request): bool => $request instanceof ListPostsRequest
        && $request->query()->all() === ['category' => 'news', 'exclude_categories' => ['news', 'archive'], 'page' => 2]);
    $mock->assertSentCount(1);
});

it('preserves immutable category lists through page cursor reset and replacement chains', function () {
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make(contractFixture('posts/index.json'))]);
    $root = postsCursorResource($mock);
    $base = $root->categories('news', 'reports')->excludeCategories('archive')->perPage(20);
    $offset = $base->page(3);
    $cursor = $offset->cursor('opaque-next');
    $first = $cursor->cursor();
    $offsetAgain = $cursor->page(1);
    $includedAgain = $cursor->categories('other');
    $excludedAgain = $cursor->excludeCategories('other');
    $filters = ['categories' => ['news', 'reports'], 'exclude_categories' => ['archive'], 'per_page' => 20];

    foreach ([$offset, $cursor, $first, $offsetAgain, $includedAgain, $excludedAgain] as $copy) {
        expect($copy)->not->toBe($base);
    }
    foreach ([
        [$root, []],
        [$base, $filters],
        [$offset, [...$filters, 'page' => 3]],
        [$cursor, [...$filters, 'pagination' => 'cursor', 'cursor' => 'opaque-next']],
        [$first, [...$filters, 'pagination' => 'cursor']],
        [$offsetAgain, [...$filters, 'page' => 1]],
        [$includedAgain, [...$filters, 'categories' => ['other'], 'pagination' => 'cursor', 'cursor' => 'opaque-next']],
        [$excludedAgain, [...$filters, 'exclude_categories' => ['other'], 'pagination' => 'cursor', 'cursor' => 'opaque-next']],
        [$base, $filters],
    ] as [$resource, $query]) {
        $resource->get();
        $mock->assertSent(fn (Request $request): bool => $request instanceof ListPostsRequest && $request->query()->all() === $query);
    }
    $mock->assertSentCount(9);
});

/** @param array<array-key, string> $slugs */
it('rejects invalid category lists locally without sending an unfiltered request', function (string $method, array $slugs) {
    $mock = new MockClient;
    $posts = postsCursorResource($mock);

    expect(fn () => $posts->{$method}(...$slugs))->toThrow(InvalidArgumentException::class);
    $mock->assertSentCount(0);
})->with(['categories', 'excludeCategories'])->with([
    'empty list' => [[]],
    'empty slug' => [['']],
    'blank slug' => [[" \t\n"]],
    'too many slugs' => [array_fill(0, 101, 'news')],
    'long ASCII slug' => [[str_repeat('x', 256)]],
    'long Unicode slug' => [[str_repeat('ü', 256)]],
    'invalid UTF-8' => [["\xFF"]],
    'invalid later slug' => [['news', '']],
    'named variadic keys' => [['named' => 'news']],
]);

it('accepts both category list bounds and counts Unicode characters without normalizing slugs', function (string $method) {
    $slugs = [str_repeat('x', 255), ...array_fill(0, 98, ' News '), str_repeat('ü', 255)];
    $key = $method === 'categories' ? 'categories' : 'exclude_categories';
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make(contractFixture('posts/index.json'))]);

    postsCursorResource($mock)->{$method}(...$slugs)->get();

    $mock->assertSent(fn (Request $request): bool => $request instanceof ListPostsRequest && $request->query()->all() === [$key => $slugs]);
    $mock->assertSentCount(1);
})->with(['categories', 'excludeCategories']);

it('rejects category and categories conflicts in either chaining order', function (bool $singleFirst) {
    $mock = new MockClient;
    $posts = postsCursorResource($mock);

    expect(fn () => $singleFirst
        ? $posts->category('news')->categories('reports')
        : $posts->categories('reports')->category('news'))->toThrow(InvalidArgumentException::class);
    $mock->assertSentCount(0);
})->with([false, true]);

it('maps backend category list validation through the existing typed SDK error', function () {
    $errors = ['categories.0' => ['The category is invalid.'], 'exclude_categories.0' => ['The exclusion is invalid.']];
    $mock = new MockClient([ListPostsRequest::class => MockResponse::make([
        'code' => 'cms_validation_failed', 'errors' => $errors,
    ], 422)]);

    try {
        postsCursorResource($mock)->categories('news')->excludeCategories('archive')->get();
    } catch (RequestValidationException $exception) {
        expect($exception->errors)->toBe($errors)->and($exception->errorCode)->toBe('cms_validation_failed');
        $mock->assertSentCount(1);

        return;
    }
    throw new RuntimeException('Expected a typed category validation exception.');
});
