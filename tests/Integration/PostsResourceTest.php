<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\ListPostsRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPostRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

it('lists posts and maps the paginated response', function () {
    $mock = new MockClient([
        ListPostsRequest::class => MockResponse::make([
            'data' => [
                [
                    'uuid' => 'u1', 'title' => 'First', 'slug' => 'first',
                    'published' => true, 'published_at' => '2026-02-01T12:00:00+00:00',
                ],
                [
                    'uuid' => 'u2', 'title' => 'Second', 'slug' => 'second',
                    'published' => true, 'published_at' => '2026-02-02T12:00:00+00:00',
                ],
            ],
            'meta' => [
                'current_page' => 1, 'last_page' => 3, 'per_page' => 2, 'total' => 5,
            ],
            'links' => ['first' => '...', 'last' => '...', 'prev' => null, 'next' => '...'],
        ], 200),
    ]);

    $connector = new MembergyConnector(
        baseUrl: 'https://members.example.org',
        tenant: 'my-club',
    );
    $connector->withMockClient($mock);

    $result = MembergyClient::fromConnector($connector)
        ->content()->posts()
        ->category('news')
        ->perPage(2)
        ->get();

    expect($result->items)->toHaveCount(2)
        ->and($result->items[0]->slug)->toBe('first')
        ->and($result->currentPage())->toBe(1)
        ->and($result->lastPage())->toBe(3)
        ->and($result->hasMorePages())->toBeTrue();

    $mock->assertSent(function (ListPostsRequest $req) {
        $query = $req->query()->all();

        return $query['category'] === 'news' && (int) $query['per_page'] === 2;
    });
});

it('fetches a single post by slug', function () {
    $mock = new MockClient([
        ShowPostRequest::class => MockResponse::make([
            'uuid' => 'u1',
            'title' => 'Hello',
            'slug' => 'hello',
            'published' => true,
            'published_at' => '2026-03-01T00:00:00+00:00',
        ], 200),
    ]);

    $connector = new MembergyConnector('https://members.example.org', 'my-club');
    $connector->withMockClient($mock);

    $post = MembergyClient::fromConnector($connector)
        ->content()->posts()
        ->find('hello');

    expect($post->title)->toBe('Hello')
        ->and($post->slug)->toBe('hello');
});

it('translates a 404 into a ResourceNotFoundException', function () {
    $mock = new MockClient([
        ShowPostRequest::class => MockResponse::make(
            body: ['message' => 'Not found'],
            status: 404,
        ),
    ]);

    $connector = new MembergyConnector('https://members.example.org', 'my-club');
    $connector->withMockClient($mock);

    MembergyClient::fromConnector($connector)
        ->content()->posts()
        ->find('does-not-exist');
})->throws(ResourceNotFoundException::class);
