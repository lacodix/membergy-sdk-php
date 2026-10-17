<?php

declare(strict_types=1);

use Lacodix\MembergySdk\DataObjects\BlockDocument;
use Lacodix\MembergySdk\DataObjects\Blocks\AnchorNavigationBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\ContactFormBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\DownloadsBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\GalleryBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\ImageBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\Link;
use Lacodix\MembergySdk\DataObjects\Blocks\LogoCloudBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\PostCategoryBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\TestimonialItem;
use Lacodix\MembergySdk\DataObjects\Blocks\TextImageBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\VideoBlock;
use Lacodix\MembergySdk\DataObjects\HeroImage;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Factories\BlockFactory;
use Lacodix\MembergySdk\Support\Data;

it('accepts scope-only and empty anchor data without manufacturing levels', function (array $data) {
    $block = (new BlockFactory)->fromArray(['id' => 'menu', 'type' => 'anchor_navigation', 'version' => 1, 'settings' => [], 'data' => $data]);
    expect($block)->toBeInstanceOf(AnchorNavigationBlock::class);
    assert($block instanceof AnchorNavigationBlock);
    expect($block->levels)->toBeNull();
    expect($block->scope)->toBe($data['scope'] ?? 'page');
    expect($block->dataToArray())->not->toHaveKey('levels');
})->with([[[]], [['scope' => 'page']], [['scope' => 'following']]]);

it('rejects malformed supplied inert levels', function (mixed $levels) {
    expect(fn () => (new BlockFactory)->fromArray(['id' => 'menu', 'type' => 'anchor_navigation', 'version' => 1, 'settings' => [], 'data' => ['levels' => $levels]]))->toThrow(HydrationException::class);
})->with(['null' => [null], 'string' => ['2'], 'mixed' => [[2, '3']], 'map' => [['a' => 2]], 'out of range' => [[7]], 'duplicate' => [[2, 2]]]);

it('hydrates the required selected or empty Form identity and retains additive fields', function (?string $uuid) {
    $block = (new BlockFactory)->fromArray(['id' => 'form', 'type' => 'contact_form', 'version' => 1, 'settings' => [], 'data' => ['form_uuid' => $uuid, 'future' => true]]);
    expect($block)->toBeInstanceOf(ContactFormBlock::class);
    assert($block instanceof ContactFormBlock);
    expect($block->formUuid)->toBe($uuid);
    expect($block->dataExtra)->toBe(['future' => true]);
    expect($block->dataToArray())->toBe(['form_uuid' => $uuid, 'future' => true]);
})->with([[null], ['11111111-1111-4111-8111-111111111111']]);

it('rejects missing old inline or invalid Form identities', function (array $data) {
    expect(fn () => (new BlockFactory)->fromArray(['id' => 'form', 'type' => 'contact_form', 'version' => 1, 'settings' => [], 'data' => $data]))->toThrow(HydrationException::class);
})->with([[[]], [['form_handle' => 'contact', 'topics' => []]], [['form_uuid' => '']], [['form_uuid' => 'not-a-uuid']], [['form_uuid' => 1]], [['form_uuid' => []]]]);

it('exposes the optional testimonial description without renaming name', function (array $optional) {
    $item = TestimonialItem::fromArray(['text' => ['format' => 'html', 'value' => '<p>Quote</p>'], 'name' => 'Erika', 'future' => 42] + $optional);
    expect($item->name)->toBe('Erika');
    expect($item->description)->toBe($optional['description'] ?? null);
    expect($item->extra)->toBe(['future' => 42]);
})->with([[[]], [['description' => null]], [['description' => 'Chair']]]);

it('rejects a malformed testimonial description', function () {
    expect(fn () => TestimonialItem::fromArray(['text' => ['format' => 'html', 'value' => ''], 'name' => 'Erika', 'description' => []]))->toThrow(HydrationException::class);
});

it('hydrates the exact backend unfinished example including every nullable reference', function () {
    $payload = json_decode((string) file_get_contents(dirname(__DIR__).'/Fixtures/Contracts/BlockWire/v1/unfinished-block-document.json'), true, flags: JSON_THROW_ON_ERROR);
    $document = BlockDocument::fromArray($payload);
    expect($document->blocks)->toHaveCount(15);
    assert($document->blocks[2] instanceof ImageBlock);
    expect($document->blocks[2]->image)->toBeNull();
    assert($document->blocks[3] instanceof TextImageBlock);
    expect($document->blocks[3]->image)->toBeNull();
    assert($document->blocks[4] instanceof GalleryBlock);
    expect($document->blocks[4]->items[0]->image)->toBeNull();
    assert($document->blocks[7] instanceof DownloadsBlock);
    expect($document->blocks[7]->items[0]->file)->toBeNull();
    assert($document->blocks[9] instanceof LogoCloudBlock);
    expect($document->blocks[9]->items[0]->image)->toBeNull();
    assert($document->blocks[10] instanceof VideoBlock);
    expect($document->blocks[10]->video)->toBeNull();
    assert($document->blocks[12] instanceof PostCategoryBlock);
    expect($document->blocks[12]->categoryUuid)->toBe('');
});

it('retains supplied optional levels metadata exactly', function (array $levels) {
    $block = (new BlockFactory)->fromArray(['id' => 'menu', 'type' => 'anchor_navigation', 'version' => 1, 'settings' => [], 'data' => ['levels' => $levels]]);
    assert($block instanceof AnchorNavigationBlock);
    expect($block->dataToArray())->toBe(['levels' => $levels, 'scope' => 'page']);
})->with([[[]], [[1, 6]]]);

it('accepts schema-proven nullable link URLs and category selection without inventing values', function () {
    $link = Link::fromArray(['url' => null, 'open_in_new_tab' => false, 'rel' => []]);
    expect($link->url)->toBeNull();
    $block = (new BlockFactory)->fromArray(['id' => 'category', 'type' => 'post_category', 'version' => 1, 'settings' => [], 'data' => ['category_uuid' => null, 'limit' => 6, 'order' => 'published_at_desc', 'include_teaser' => true]]);
    assert($block instanceof PostCategoryBlock);
    expect($block->categoryUuid)->toBeNull();
});

it('requires a string URL inside a supplied non-block hero link', function (array $url) {
    $image = Data::objectList(Data::object(Data::object(contractFixture('pages/show.json'), 'data'), 'hero'), 'images')[0];
    $image['link'] = $url + ['open_in_new_tab' => false, 'rel' => []];
    expect(fn () => HeroImage::fromArray($image))->toThrow(HydrationException::class);
})->with([
    'explicit null' => [['url' => null]],
    'missing URL' => [[]],
    'integer URL' => [['url' => 42]],
    'array URL' => [['url' => []]],
]);

it('keeps absent hero links and valid string hero URLs unchanged', function () {
    $image = Data::objectList(Data::object(Data::object(contractFixture('pages/show.json'), 'data'), 'hero'), 'images')[0];
    $image['link'] = null;
    expect(HeroImage::fromArray($image)->link)->toBeNull();
    $image['link'] = ['url' => 'https://example.org/hero', 'open_in_new_tab' => false, 'rel' => []];
    expect(HeroImage::fromArray($image)->link?->url)->toBe('https://example.org/hero');
});

it('rejects malformed supplied values in nullable block slots', function (int $index, string $path, mixed $value) {
    $payload = json_decode((string) file_get_contents(dirname(__DIR__).'/Fixtures/Contracts/BlockWire/v1/unfinished-block-document.json'), true, flags: JSON_THROW_ON_ERROR);
    $block = $payload['blocks'][$index];
    $slot = &$block['data'];
    foreach (explode('.', $path) as $key) {
        $slot = &$slot[$key];
    }
    $slot = $value;
    unset($slot);
    expect(fn () => (new BlockFactory)->fromArray($block))->toThrow(HydrationException::class);
})->with([
    'image scalar' => [2, 'image', false],
    'text-image scalar' => [3, 'image', 'invalid'],
    'gallery item list' => [4, 'items.0.image', [1]],
    'download missing UUID' => [7, 'items.0.file', []],
    'logo invalid UUID type' => [9, 'items.0.image', ['uuid' => 42]],
    'video scalar' => [10, 'video', 42],
    'poster scalar' => [10, 'poster', true],
    'category object' => [12, 'category_uuid', []],
    'link URL integer' => [2, 'link', ['url' => 42, 'open_in_new_tab' => false, 'rel' => []]],
    'link URL array' => [2, 'link', ['url' => [], 'open_in_new_tab' => false, 'rel' => []]],
]);
