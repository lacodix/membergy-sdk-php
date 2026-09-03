<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Compatibility;
use Lacodix\MembergySdk\Exceptions\UnsupportedApiVersionException;
use Lacodix\MembergySdk\Media\ImageUrlBuilder;
use Lacodix\MembergySdk\Media\MediaUrlFactory;
use Lacodix\MembergySdk\MembergyClient;

it('builds deterministic Glide URLs and responsive srcsets immutably', function () {
    $base = new ImageUrlBuilder('https://api.membergy.test/image/uuid');
    $image = $base
        ->width(1600)
        ->height(900)
        ->focus(0.52, 0.38)
        ->format('webp')
        ->quality(82);

    expect($base->url())->toBe('https://api.membergy.test/image/uuid')
        ->and($image->url())->toBe(
            'https://api.membergy.test/image/uuid?fit=crop-52-38&fm=webp&h=900&q=82&w=1600',
        )
        ->and($image->srcset([960, 320, 640, 640]))->toBe(
            'https://api.membergy.test/image/uuid?fit=crop-52-38&fm=webp&h=900&q=82&w=320 320w, '
            .'https://api.membergy.test/image/uuid?fit=crop-52-38&fm=webp&h=900&q=82&w=640 640w, '
            .'https://api.membergy.test/image/uuid?fit=crop-52-38&fm=webp&h=900&q=82&w=960 960w',
        );
});

it('builds tenant-scoped public media URLs and rejects unsafe identifiers', function () {
    $factory = new MediaUrlFactory('https://members.example.org/api/v1', 'my club');

    expect($factory->image('11111111-1111-4111-8111-111111111111')->width(640)->url())
        ->toBe(
            'https://members.example.org/api/v1/tenant/my%20club/content/image/'
            .'11111111-1111-4111-8111-111111111111?w=640',
        )
        ->and($factory->file('33333333-3333-4333-8333-333333333333'))
        ->toEndWith('/content/file/33333333-3333-4333-8333-333333333333');

    expect(fn () => $factory->image('../secret'))->toThrow(InvalidArgumentException::class);
});

it('exposes and enforces the SDK to API compatibility matrix', function () {
    $client = new MembergyClient('https://members.example.org', 'demo');

    expect($client->compatibility())->toBe(Compatibility::matrix())
        ->and($client->compatibility()['api'])->toBe('v1')
        ->and($client->compatibility()['block_document_schema'])->toBe(1)
        ->and(fn () => new MembergyClient(
            'https://members.example.org',
            'demo',
            apiVersion: 'v2',
        ))->toThrow(UnsupportedApiVersionException::class);
});
