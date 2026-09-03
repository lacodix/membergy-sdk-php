<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\FileReference;
use Lacodix\MembergySdk\DataObjects\Image;
use Lacodix\MembergySdk\DataObjects\Menu;
use Lacodix\MembergySdk\DataObjects\MenuSummary;
use Lacodix\MembergySdk\DataObjects\Page;
use Lacodix\MembergySdk\DataObjects\PageSummary;
use Lacodix\MembergySdk\DataObjects\Post;
use Lacodix\MembergySdk\DataObjects\PostCategory;
use Lacodix\MembergySdk\DataObjects\PostSummary;
use Lacodix\MembergySdk\Exceptions\AuthenticationException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\ListFilesRequest;
use Lacodix\MembergySdk\Requests\Content\ListImagesRequest;
use Lacodix\MembergySdk\Requests\Content\ListMenusRequest;
use Lacodix\MembergySdk\Requests\Content\ListPagesRequest;
use Lacodix\MembergySdk\Requests\Content\ListPostCategoriesRequest;
use Lacodix\MembergySdk\Requests\Content\ListPostsRequest;
use Lacodix\MembergySdk\Requests\Content\ShowFileRequest;
use Lacodix\MembergySdk\Requests\Content\ShowImageRequest;
use Lacodix\MembergySdk\Requests\Content\ShowMenuRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPageRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPostCategoryRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPostRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

function cmsClient(MockClient $mock, ?string $token = null): MembergyClient
{
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'demo',
        userToken: $token,
    );
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

it('lists and fetches pages through the exact versioned paths and dynamic include query', function () {
    $show = contractFixture('pages/show.json');
    $show['included'] = contractFixture('dynamic-includes.json');
    $mock = new MockClient([
        ListPagesRequest::class => MockResponse::make(contractFixture('pages/index.json')),
        ShowPageRequest::class => MockResponse::make($show),
    ]);
    $client = cmsClient($mock, 'member-token');

    $pages = $client->content()->pages()->page(1)->perPage(20)->get();
    $page = $client->content()->pages()->find('willkommen', includeDynamic: true);

    expect($pages->items[0])->toBeInstanceOf(PageSummary::class);
    expect($pages->currentPage())->toBe(1);
    expect($page)->toBeInstanceOf(Page::class);
    expect($page->included?->dynamic)->toHaveCount(1);

    $mock->assertSent(function (Request $request): bool {
        return $request instanceof ListPagesRequest
            && $request->resolveEndpoint() === '/tenant/demo/content/pages'
            && $request->query()->all() === ['page' => 1, 'per_page' => 20];
    });
    $mock->assertSent(function (ShowPageRequest $request, Response $response): bool {
        $pending = $response->getPendingRequest();

        return $request->resolveEndpoint() === '/tenant/demo/content/pages/willkommen'
            && $request->query()->all() === ['include' => 'dynamic']
            && $pending->getUrl() === 'https://api.membergy.test/api/v1/tenant/demo/content/pages/willkommen'
            && $pending->headers()->get('Authorization') === 'Bearer member-token'
            && $pending->headers()->get('Accept') === 'application/json';
    });
});

it('lists and fetches posts with only contract-supported filters', function () {
    $show = contractFixture('posts/show.json');
    $show['included'] = contractFixture('dynamic-includes.json');
    $mock = new MockClient([
        ListPostsRequest::class => MockResponse::make(contractFixture('posts/index.json')),
        ShowPostRequest::class => MockResponse::make($show),
    ]);
    $client = cmsClient($mock);

    $posts = $client->content()->posts()->category('aktuelles')->perPage(20)->get();
    $post = $client->content()->posts()->find('erfolg-beim-wertungsspiel', includeDynamic: true);

    expect($posts->items[0])->toBeInstanceOf(PostSummary::class);
    expect($post)->toBeInstanceOf(Post::class);
    expect($post->category?->slug)->toBe('aktuelles');

    $mock->assertSent(function (Request $request): bool {
        return $request instanceof ListPostsRequest
            && $request->resolveEndpoint() === '/tenant/demo/content/posts'
            && $request->query()->all() === ['category' => 'aktuelles', 'per_page' => 20];
    });
    $mock->assertSent(function (ShowPostRequest $request): bool {
        return $request->resolveEndpoint() === '/tenant/demo/content/posts/erfolg-beim-wertungsspiel'
            && $request->query()->all() === ['include' => 'dynamic'];
    });
});

it('lists and fetches post categories by public slug', function () {
    $mock = new MockClient([
        ListPostCategoriesRequest::class => MockResponse::make(contractFixture('post-categories/index.json')),
        ShowPostCategoryRequest::class => MockResponse::make(contractFixture('post-categories/show.json')),
    ]);
    $client = cmsClient($mock);

    $categories = $client->content()->postCategories()->get();
    $category = $client->content()->postCategories()->find('aktuelles');

    expect($categories->items[0])->toBeInstanceOf(PostCategory::class);
    expect($category->uuid)->toBe('20000000-0000-4000-8000-000000000001');
    $mock->assertSent(function (ShowPostCategoryRequest $request): bool {
        return $request->resolveEndpoint() === '/tenant/demo/content/post-categories/aktuelles';
    });
});

it('lists and fetches arbitrarily nested menus by public handle', function () {
    $mock = new MockClient([
        ListMenusRequest::class => MockResponse::make(contractFixture('menus/index.json')),
        ShowMenuRequest::class => MockResponse::make(contractFixture('menus/show.json')),
    ]);
    $client = cmsClient($mock);

    $menus = $client->content()->menus()->get();
    $menu = $client->content()->menus()->find('main');

    expect($menus->items[0])->toBeInstanceOf(MenuSummary::class);
    expect($menu)->toBeInstanceOf(Menu::class);
    expect($menu->items[0]->children[0]->children)->toHaveCount(1);
    $mock->assertSent(function (ShowMenuRequest $request): bool {
        return $request->resolveEndpoint() === '/tenant/demo/content/menus/main';
    });
});

it('lists and fetches typed images, files, and videos through their binary identifiers', function () {
    $imageMock = new MockClient([
        ListImagesRequest::class => MockResponse::make(contractFixture('media/images-index.json')),
        ShowImageRequest::class => MockResponse::make(contractFixture('media/image-show.json')),
    ]);
    $fileMock = new MockClient([
        ListFilesRequest::class => MockResponse::make(contractFixture('media/files-index.json')),
        ShowFileRequest::class => MockResponse::make(contractFixture('media/video-show.json')),
    ]);

    $images = cmsClient($imageMock)->content()->images()->get();
    $image = cmsClient($imageMock)->content()->images()->find('11111111-1111-4111-8111-111111111111');
    $files = cmsClient($fileMock)->content()->files()->get();
    $video = cmsClient($fileMock)->content()->files()->find('44444444-4444-4444-8444-444444444444');

    expect($images->items[0])->toBeInstanceOf(Image::class);
    expect($image)->toBeInstanceOf(Image::class);
    expect($files->items[0])->toBeInstanceOf(FileReference::class);
    expect($files->items[1]->kind)->toBe('video');
    expect($video)->toBeInstanceOf(FileReference::class);
    expect($video->kind)->toBe('video');
    $imageMock->assertSent(function (ShowImageRequest $request): bool {
        return $request->resolveEndpoint() === '/tenant/demo/content/images/11111111-1111-4111-8111-111111111111';
    });
    $fileMock->assertSent(function (ShowFileRequest $request): bool {
        return $request->resolveEndpoint() === '/tenant/demo/content/files/44444444-4444-4444-8444-444444444444';
    });
});

it('maps a public 404 to the stable SDK not-found exception', function () {
    $mock = new MockClient([
        ShowPageRequest::class => MockResponse::make(['message' => 'Not found'], 404),
    ]);

    cmsClient($mock)->content()->pages()->find('missing');
})->throws(ResourceNotFoundException::class, "Page with slug 'missing' not found.");

it('maps a paginated public 404 to the stable SDK not-found exception', function () {
    $mock = new MockClient([
        ListPagesRequest::class => MockResponse::make(contractFixture('errors/not-found.json'), 404),
    ]);

    cmsClient($mock)->content()->pages()->get();
})->throws(ResourceNotFoundException::class, 'The requested Membergy resource was not found.');

it('maps the versioned authentication and validation errors to typed SDK exceptions', function () {
    $authenticationMock = new MockClient([
        ListPagesRequest::class => MockResponse::make(contractFixture('errors/unauthenticated.json'), 401),
    ]);

    expect(fn () => cmsClient($authenticationMock, 'revoked-token')->content()->pages()->get())
        ->toThrow(AuthenticationException::class, 'The Membergy API rejected the supplied user token.');

    $validationMock = new MockClient([
        ListPagesRequest::class => MockResponse::make(contractFixture('errors/validation.json'), 422),
    ]);

    try {
        cmsClient($validationMock)->content()->pages()->get();
    } catch (RequestValidationException $exception) {
        expect($exception->errors)->toBe(['sort' => ['Sort ist unzulässig.']]);

        return;
    }

    throw new RuntimeException('Expected a typed request validation exception.');
});

it('rejects pagination values that the public contract does not accept', function (int $perPage) {
    cmsClient(new MockClient)->content()->pages()->perPage($perPage);
})->with([0, 101])->throws(InvalidArgumentException::class);
