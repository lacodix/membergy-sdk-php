<?php

declare(strict_types=1);

use Lacodix\MembergySdk\DataObjects\BlockDocument;
use Lacodix\MembergySdk\DataObjects\Blocks\AgendaBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\AnchorNavigationBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\Block;
use Lacodix\MembergySdk\DataObjects\Blocks\ColumnsBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\ContactFormBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\DownloadsBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\GalleryBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\ImageBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\LogoCloudBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\PostCategoryBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\RawHtmlBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\TestimonialsBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\TextBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\TextImageBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\TitleBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\UnknownBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\VideoBlock;
use Lacodix\MembergySdk\DataObjects\DynamicIncludes;
use Lacodix\MembergySdk\DataObjects\DynamicIncludes\PostCategoryInclude;
use Lacodix\MembergySdk\DataObjects\FileReference;
use Lacodix\MembergySdk\DataObjects\Image;
use Lacodix\MembergySdk\DataObjects\Menu;
use Lacodix\MembergySdk\DataObjects\MenuTargets\CustomLinkMenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\PageMenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\PostCategoryMenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\PostMenuTarget;
use Lacodix\MembergySdk\DataObjects\MenuTargets\UnknownMenuTarget;
use Lacodix\MembergySdk\DataObjects\Page;
use Lacodix\MembergySdk\DataObjects\PageSummary;
use Lacodix\MembergySdk\DataObjects\Post;
use Lacodix\MembergySdk\DataObjects\PostCategory;
use Lacodix\MembergySdk\DataObjects\PostSummary;
use Lacodix\MembergySdk\Factories\BlockFactory;
use Lacodix\MembergySdk\Factories\MediaFactory;
use Lacodix\MembergySdk\Factories\MenuTargetFactory;
use Lacodix\MembergySdk\MenuUrlResolver;
use Lacodix\MembergySdk\Support\Data;

it('hydrates page summary and detail fixtures into typed recursive DTOs', function () {
    $index = contractFixture('pages/index.json');
    $summary = PageSummary::fromArray(Data::objectList($index, 'data')[0]);
    $show = contractFixture('pages/show.json');
    $page = Page::fromArray(Data::object($show, 'data'));

    expect($summary->slug)->toBe('willkommen');
    expect($summary->heroMedia?->kind)->toBe('image');
    expect($page->hero->images[0]->media->uuid)->toBe('11111111-1111-4111-8111-111111111111');
    expect($page->content->blocks)->toHaveCount(6);
    expect($page->content->blocks[0])->toBeInstanceOf(TitleBlock::class);
    expect($page->content->blocks[1])->toBeInstanceOf(TextBlock::class);
    expect($page->content->blocks[2])->toBeInstanceOf(ImageBlock::class);
    expect($page->content->blocks[3])->toBeInstanceOf(TextImageBlock::class);
    expect($page->content->blocks[4])->toBeInstanceOf(GalleryBlock::class);
    expect($page->content->blocks[5])->toBeInstanceOf(ColumnsBlock::class);

    $text = $page->content->blocks[1];
    assert($text instanceof TextBlock);
    expect($text->title)->toBe('Über uns');

    $columns = $page->content->blocks[5];
    assert($columns instanceof ColumnsBlock);
    expect($columns->title)->toBe('Proben');
    expect($columns->columns[0]->width)->toBe('one_third');
    expect($columns->columns[1]->width)->toBe('two_thirds');
    expect($columns->columns[0]->blocks[0])->toBeInstanceOf(TitleBlock::class);
    expect($columns->columns[1]->blocks[0])->toBeInstanceOf(TextBlock::class);
});

it('hydrates posts, categories, dynamic includes, and media fixtures', function () {
    $postIndex = contractFixture('posts/index.json');
    $postSummary = PostSummary::fromArray(Data::objectList($postIndex, 'data')[0]);
    $postShow = contractFixture('posts/show.json');
    $includes = contractFixture('dynamic-includes.json');
    $post = Post::fromArray(Data::object($postShow, 'data'), $includes);
    $category = PostCategory::fromArray(Data::object(contractFixture('post-categories/show.json'), 'data'));
    $factory = new MediaFactory;
    $image = $factory->fromArray(Data::object(contractFixture('media/image-show.json'), 'data'));
    $file = $factory->fromArray(Data::object(contractFixture('media/file-show.json'), 'data'));
    $video = $factory->fromArray(Data::object(contractFixture('media/video-show.json'), 'data'));

    expect($postSummary->category?->slug)->toBe('aktuelles');
    expect($post->content)->toBeInstanceOf(BlockDocument::class);
    expect($post->included)->toBeInstanceOf(DynamicIncludes::class);
    expect($post->included?->dynamic[0])->toBeInstanceOf(PostCategoryInclude::class);
    expect($category->position)->toBe(10);
    expect($image)->toBeInstanceOf(Image::class);
    expect($file)->toBeInstanceOf(FileReference::class);
    expect($video)->toBeInstanceOf(FileReference::class);
    expect($video->kind)->toBe('video');
});

it('keeps additive fields and unknown block types as forward-compatible fallbacks', function () {
    $pageFixture = contractFixture('pages/show.json');
    $data = Data::object($pageFixture, 'data');
    $data['future_field'] = ['enabled' => true];
    $page = Page::fromArray($data);

    $unknown = (new BlockFactory)->fromArray([
        'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa99',
        'type' => 'future_block',
        'version' => 7,
        'data' => ['future' => true],
        'settings' => ['future_setting' => 'preserved'],
        'future_top_level' => 42,
    ]);

    expect($page->extra)->toHaveKey('future_field');
    expect($unknown)->toBeInstanceOf(UnknownBlock::class);
    assert($unknown instanceof UnknownBlock);
    expect($unknown->data)->toBe(['future' => true]);
    expect($unknown->settings->extra)->toBe(['future_setting' => 'preserved']);
    expect($unknown->raw)->toHaveKey('future_top_level', 42);
});

it('keeps the SDK block factory synchronized with the backend registry', function () {
    $registry = contractFixture('block-registry.json');
    $definitions = Data::objectList($registry, 'blocks');
    $expected = [];

    foreach ($definitions as $definition) {
        $expected[Data::string($definition, 'type')] = Data::int($definition, 'version');
    }

    expect(BlockFactory::knownTypes())->toBe($expected);
});

/**
 * @param  class-string<Block>  $class
 * @param  array<string, mixed>  $data
 */
it('hydrates every remaining v1 block into its concrete DTO', function (
    string $type,
    string $class,
    array $data,
) {
    $block = (new BlockFactory)->fromArray([
        'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa98',
        'type' => $type,
        'version' => 1,
        'data' => $data,
        'settings' => [],
    ]);

    expect($block)->toBeInstanceOf($class);
})->with([
    'raw html' => ['raw_html', RawHtmlBlock::class, ['html' => '<p>Safe</p>']],
    'downloads' => ['downloads', DownloadsBlock::class, [
        'title' => null,
        'items' => [['label' => 'PDF', 'file' => ['uuid' => '33333333-3333-4333-8333-333333333333']]],
    ]],
    'agenda' => ['agenda', AgendaBlock::class, [
        'title' => null,
        'intro' => null,
        'items' => [[
            'time' => null,
            'title' => 'Einlass',
            'text' => null,
            'highlight' => false,
            'link' => null,
            'file' => null,
        ]],
    ]],
    'logo cloud' => ['logo_cloud', LogoCloudBlock::class, [
        'title' => null,
        'items' => [[
            'image' => ['uuid' => '11111111-1111-4111-8111-111111111111', 'alt' => null],
            'link' => null,
        ]],
    ]],
    'video' => ['video', VideoBlock::class, [
        'video' => ['uuid' => '44444444-4444-4444-8444-444444444444'],
        'poster' => null,
        'caption' => null,
    ]],
    'testimonials' => ['testimonials', TestimonialsBlock::class, [
        'title' => null,
        'per_page' => 1,
        'autoplay' => false,
        'items' => [['text' => ['format' => 'html', 'value' => '<p>Sehr gut.</p>'], 'name' => 'Alex']],
    ]],
    'post category' => ['post_category', PostCategoryBlock::class, [
        'category_uuid' => '20000000-0000-4000-8000-000000000001',
        'limit' => 3,
        'order' => 'published_at_desc',
        'include_teaser' => true,
    ]],
    'contact form' => ['contact_form', ContactFormBlock::class, [
        'form_handle' => 'contact',
        'topics' => [['key' => 'general', 'label' => 'Allgemein']],
    ]],
    'anchor navigation' => ['anchor_navigation', AnchorNavigationBlock::class, [
        'levels' => [2, 3],
    ]],
]);

it('hydrates every menu target recursively and resolves consumer URLs explicitly', function () {
    $menu = Menu::fromArray(Data::object(contractFixture('menus/show.json'), 'data'));

    expect($menu->items[0]->target)->toBeInstanceOf(PageMenuTarget::class);
    expect($menu->items[0]->children[0]->target)->toBeInstanceOf(PostCategoryMenuTarget::class);
    expect($menu->items[0]->children[0]->children[0]->target)->toBeInstanceOf(PostMenuTarget::class);
    expect($menu->items[1]->target)->toBeInstanceOf(CustomLinkMenuTarget::class);

    $unknown = (new MenuTargetFactory)->fromArray(['type' => 'future_target', 'key' => 'kept']);
    expect($unknown)->toBeInstanceOf(UnknownMenuTarget::class);

    $resolver = new MenuUrlResolver([
        'page' => static fn ($target): string => '/'.$target->slug,
        'post' => static fn ($target): string => '/news/'.$target->slug,
        'post_category' => static fn ($target): string => '/news/category/'.$target->slug,
    ]);

    expect($resolver->resolve($menu->items[0]->target))->toBe('/willkommen');
    expect($resolver->resolve($menu->items[0]->children[0]->children[0]->target))
        ->toBe('/news/erfolg-beim-wertungsspiel');
    expect($resolver->resolve($menu->items[1]->target))->toBe('https://shop.example.org/noten');
    expect($resolver->resolve($unknown))->toBeNull();
});
