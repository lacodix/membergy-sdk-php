<?php

declare(strict_types=1);

use Lacodix\MembergySdk\DataObjects\Blocks\AnchorNavigationBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\BlockSettings;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Factories\BlockFactory;

it('hydrates explicit anchor scope and roundtrips its wire data', function (string $scope) {
    $payload = ['id' => 'menu', 'type' => 'anchor_navigation', 'version' => 1,
        'data' => ['levels' => [2, 3], 'scope' => $scope, 'future' => 'preserved'], 'settings' => []];
    $block = (new BlockFactory)->fromArray($payload);
    expect($block)->toBeInstanceOf(AnchorNavigationBlock::class);
    assert($block instanceof AnchorNavigationBlock);
    expect($block->scope)->toBe($scope);
    expect($block->dataToArray())->toBe($payload['data']);
    $roundtrip = array_replace($payload, ['data' => $block->dataToArray()]);
    expect((new BlockFactory)->fromArray($roundtrip))->toEqual($block);
})->with(['page', 'following']);

it('defaults absent scope and existing positional construction to page', function () {
    $payload = ['id' => 'legacy', 'type' => 'anchor_navigation', 'version' => 1,
        'data' => ['levels' => [2]], 'settings' => []];
    $block = (new BlockFactory)->fromArray($payload);
    expect($block)->toBeInstanceOf(AnchorNavigationBlock::class);
    assert($block instanceof AnchorNavigationBlock);
    expect($block->scope)->toBe('page');
    expect($block->dataToArray())->toBe(['levels' => [2], 'scope' => 'page']);
    $direct = new AnchorNavigationBlock('direct', 1, BlockSettings::fromArray([]), [2], ['future' => 1], ['outer' => 2]);
    expect($direct->scope)->toBe('page');
    expect($direct->dataExtra)->toBe(['future' => 1]);
    expect($direct->extra)->toBe(['outer' => 2]);
});

it('rejects invalid supplied anchor scopes without treating them as missing', function (mixed $scope) {
    expect(fn () => (new BlockFactory)->fromArray([
        'id' => 'invalid', 'type' => 'anchor_navigation', 'version' => 1,
        'data' => ['levels' => [2], 'scope' => $scope], 'settings' => [],
    ]))->toThrow(HydrationException::class);
})->with(['unknown' => ['section'], 'null' => [null], 'integer' => [1], 'array' => [[]], 'empty' => ['']]);
