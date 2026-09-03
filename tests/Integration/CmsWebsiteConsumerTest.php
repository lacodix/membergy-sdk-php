<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\Blocks\ColumnsBlock;
use Lacodix\MembergySdk\DataObjects\MenuTargets\InternalMenuTarget;
use Lacodix\MembergySdk\DataObjects\Page;
use Lacodix\MembergySdk\DataObjects\PostSummary;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\MenuUrlResolver;
use Lacodix\MembergySdk\Requests\Content\ListPostsRequest;
use Lacodix\MembergySdk\Requests\Content\ShowFileRequest;
use Lacodix\MembergySdk\Requests\Content\ShowImageRequest;
use Lacodix\MembergySdk\Requests\Content\ShowMenuRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPageRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

it('consumes a complete website snapshot through the public SDK surface', function () {
    $pageFixture = contractFixture('pages/show.json');
    $pageFixture['included'] = contractFixture('dynamic-includes.json');
    $mock = new MockClient([
        ShowMenuRequest::class => MockResponse::make(contractFixture('menus/show.json')),
        ShowPageRequest::class => MockResponse::make($pageFixture),
        ListPostsRequest::class => MockResponse::make(contractFixture('posts/index.json')),
        ShowImageRequest::class => MockResponse::make(contractFixture('media/image-show.json')),
        ShowFileRequest::class => MockResponse::make(contractFixture('media/file-show.json')),
    ]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo');
    $connector->withMockClient($mock);
    $client = MembergyClient::fromConnector($connector);

    $menu = $client->content()->menus()->find('main');
    $page = $client->content()->pages()->find('willkommen', includeDynamic: true);
    $posts = $client->content()->posts()->category('aktuelles')->get();
    $image = $client->content()->images()->find('11111111-1111-4111-8111-111111111111');
    $file = $client->content()->files()->find('33333333-3333-4333-8333-333333333333');

    $urls = new MenuUrlResolver([
        'page' => static fn (InternalMenuTarget $target): string => '/'.$target->slug,
        'post' => static fn (InternalMenuTarget $target): string => '/beitraege/'.$target->slug,
        'post_category' => static fn (InternalMenuTarget $target): string => '/beitraege/'.$target->slug,
    ]);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->content->schemaVersion)->toBe(1)
        ->and($page->content->blocks)->toHaveCount(6)
        ->and($page->content->blocks[5])->toBeInstanceOf(ColumnsBlock::class)
        ->and($page->included?->dynamic)->toHaveCount(1)
        ->and($posts->items[0])->toBeInstanceOf(PostSummary::class)
        ->and($menu->items[0]->children[0]->children)->toHaveCount(1)
        ->and($urls->resolve($menu->items[0]->target))->toBe('/willkommen')
        ->and($urls->resolve($menu->items[1]->target))->toBe('https://shop.example.org/noten')
        ->and($image->url)->toContain('/content/image/'.$image->uuid)
        ->and($file->url)->toContain('/content/file/'.$file->uuid);

    $expectedEndpoints = [
        '/tenant/demo/content/menus/main',
        '/tenant/demo/content/pages/willkommen',
        '/tenant/demo/content/posts',
        '/tenant/demo/content/images/11111111-1111-4111-8111-111111111111',
        '/tenant/demo/content/files/33333333-3333-4333-8333-333333333333',
    ];

    foreach ($expectedEndpoints as $endpoint) {
        $mock->assertSent(fn (Request $request): bool => $request->resolveEndpoint() === $endpoint);
    }
});
