<?php

declare(strict_types=1);

use Lacodix\MembergySdk\DataObjects\Blocks\MemberContinuationBlock;
use Lacodix\MembergySdk\DataObjects\Page;
use Lacodix\MembergySdk\DataObjects\PageSearchHit;
use Lacodix\MembergySdk\DataObjects\PageSummary;
use Lacodix\MembergySdk\DataObjects\Post;
use Lacodix\MembergySdk\DataObjects\PostSearchHit;
use Lacodix\MembergySdk\DataObjects\PostSummary;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Factories\BlockFactory;

it('retains server continuation metadata and the exact guest or member projection', function (string $family, string $reader) {
    $payload = contractFixture($family.'/member-continuation-'.$reader.'.json')['data'];
    $detail = $family === 'pages' ? Page::fromArray($payload) : Post::fromArray($payload);
    $summary = $family === 'pages' ? PageSummary::fromArray($payload) : PostSummary::fromArray($payload);
    expect(property_exists($detail, 'hasMemberContinuation'))->toBeTrue();
    expect($detail->hasMemberContinuation)->toBeTrue();
    expect($summary->hasMemberContinuation)->toBeTrue();
    expect($detail->content->blocks)->toHaveCount($reader === 'guest' ? 1 : 2);
    expect($detail->visibility)->toBe('public_with_member_continuation');
    expect($detail->extra)->toHaveKey('has_member_continuation', true);
    expect($summary->extra)->toHaveKey('has_member_continuation', true);
})->with([['pages', 'guest'], ['pages', 'member'], ['posts', 'guest'], ['posts', 'member']]);

it('types editorial markers without treating them as content or authorization', function () {
    $block = (new BlockFactory)->fromArray(['id' => 'marker', 'type' => 'member_continuation', 'version' => 1, 'settings' => [], 'data' => ['future' => true], 'future_outer' => true]);
    expect($block)->toBeInstanceOf(MemberContinuationBlock::class);
    assert($block instanceof MemberContinuationBlock);
    expect($block->dataExtra)->toBe(['future' => true]);
    expect($block->extra)->toBe(['future_outer' => true]);
});

it('rejects malformed supplied continuation metadata', function () {
    $payload = contractFixture('posts/show.json')['data'];
    $payload['has_member_continuation'] = 'true';
    expect(fn () => Post::fromArray($payload))->toThrow(HydrationException::class);
});

it('keeps absent metadata compatible without inferring member access from visibility', function () {
    $payload = contractFixture('posts/show.json')['data'];
    unset($payload['has_member_continuation']);
    $payload['visibility'] = 'public_with_member_continuation';
    expect(Post::fromArray($payload)->hasMemberContinuation)->toBeFalse();
    expect(PostSummary::fromArray($payload)->hasMemberContinuation)->toBeFalse();
});

it('types page and post search continuation metadata while preserving existing extras', function (int $index) {
    $payload = contractFixture('search/index.json')['data'][$index]['items'][0];
    foreach ([false, true] as $flag) {
        $payload['has_member_continuation'] = $flag;
        $hit = $index === 0 ? PageSearchHit::fromArray($payload) : PostSearchHit::fromArray($payload);
        expect(property_exists($hit, 'hasMemberContinuation'))->toBeTrue();
        expect($hit->hasMemberContinuation)->toBe($flag);
        expect($hit->extra)->toHaveKey('has_member_continuation', $flag);
    }
    unset($payload['has_member_continuation']);
    expect(($index === 0 ? PageSearchHit::fromArray($payload) : PostSearchHit::fromArray($payload))->hasMemberContinuation)->toBeFalse();
})->with([0, 1]);

it('rejects malformed continuation metadata in search hits', function (int $index) {
    $payload = contractFixture('search/index.json')['data'][$index]['items'][0];
    $payload['has_member_continuation'] = 'true';
    expect(fn () => $index === 0 ? PageSearchHit::fromArray($payload) : PostSearchHit::fromArray($payload))->toThrow(HydrationException::class);
})->with([0, 1]);
