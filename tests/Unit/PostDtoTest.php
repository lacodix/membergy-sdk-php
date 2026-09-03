<?php

declare(strict_types=1);

use Lacodix\MembergySdk\DataObjects\MediaReference;
use Lacodix\MembergySdk\DataObjects\Post;

it('hydrates from a typical Resourcerer payload', function () {
    $post = Post::fromArray([
        'uuid' => '0c1a8f02-5f3c-4b7f-9e5a-1f1b1f1b1f1b',
        'title' => 'Summer Kick-off',
        'slug' => 'summer-kick-off',
        'category' => 'news',
        'category_uuid' => 'd2d2d2d2-d2d2-d2d2-d2d2-d2d2d2d2d2d2',
        'visibility' => 'public',
        'published' => true,
        'published_at' => '2026-01-15T10:00:00+00:00',
        'content' => ['blocks' => [['type' => 'paragraph', 'text' => 'Hello']]],
        'media' => [
            'uuid' => 'mmmmmmmm-mmmm-mmmm-mmmm-mmmmmmmmmmmm',
            'url' => 'https://example.org/media/hero.jpg',
            'name' => 'hero.jpg',
            'mime_type' => 'image/jpeg',
        ],
    ]);

    expect($post->title)->toBe('Summer Kick-off')
        ->and($post->slug)->toBe('summer-kick-off')
        ->and($post->published)->toBeTrue()
        ->and($post->publishedAt?->format('Y-m-d'))->toBe('2026-01-15')
        ->and($post->content)->toBeArray()
        ->and($post->media)->toBeInstanceOf(MediaReference::class)
        ->and($post->media->url)->toBe('https://example.org/media/hero.jpg');
});

it('tolerates missing optional fields', function () {
    $post = Post::fromArray([
        'uuid' => 'u',
        'title' => 'Minimal',
        'slug' => 'minimal',
    ]);

    expect($post->category)->toBeNull()
        ->and($post->publishedAt)->toBeNull()
        ->and($post->media)->toBeNull()
        ->and($post->published)->toBeFalse();
});

it('preserves unknown keys in the extra bag', function () {
    $post = Post::fromArray([
        'uuid' => 'u',
        'title' => 't',
        'slug' => 's',
        'future_field' => 'future value',
    ]);

    expect($post->extra)->toHaveKey('future_field', 'future value');
});
