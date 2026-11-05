<?php

declare(strict_types=1);

use Lacodix\MembergySdk\DataObjects\Blocks\TableBlock;
use Lacodix\MembergySdk\DataObjects\Blocks\UnknownBlock;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Factories\BlockFactory;

it('rejects malformed table structures without coercing cells', function (array $data) {
    expect(fn () => (new BlockFactory)->fromArray(['id' => 'table', 'type' => 'table', 'version' => 1, 'settings' => [], 'data' => $data]))->toThrow(HydrationException::class);
})->with([
    'missing headers' => [['rows' => [['cell']]]],
    'scalar headers' => [['headers' => 'Heading', 'rows' => []]],
    'scalar rows' => [['headers' => ['Heading'], 'rows' => 'cell']],
    'numeric cell' => [['headers' => ['Heading'], 'rows' => [[42]]]],
    'ragged rows' => [['headers' => ['A', 'B'], 'rows' => [['cell']]]],
    'empty no-header table' => [['headers' => [], 'rows' => []]],
    'empty row' => [['headers' => [], 'rows' => [[]]]],
    'inconsistent header flag' => [['headers' => ['A'], 'rows' => [], 'has_header' => false]],
    'nonboolean header flag' => [['headers' => ['A'], 'rows' => [], 'has_header' => 'true']],
    'too many columns' => [['headers' => array_fill(0, 13, ''), 'rows' => []]],
    'too many logical rows' => [['headers' => ['A'], 'rows' => array_fill(0, 101, [''])]],
    'too long cell' => [['headers' => [], 'rows' => [[str_repeat('ü', 2001)]]]],
]);

it('retains additive table fields and unknown table versions', function () {
    $payload = ['id' => 'table', 'type' => 'table', 'version' => 1, 'settings' => ['future_setting' => true], 'data' => ['title' => null, 'headers' => ['H'], 'rows' => [], 'future' => 'kept'], 'future_outer' => 42];
    $table = (new BlockFactory)->fromArray($payload);
    assert($table instanceof TableBlock);
    expect($table->hasHeader)->toBeTrue();
    expect($table->dataExtra)->toBe(['future' => 'kept']);
    expect($table->settings->extra)->toBe(['future_setting' => true]);
    expect($table->extra)->toBe(['future_outer' => 42]);
    $payload['version'] = 2;
    $payload['data'] = ['future' => true];
    $unknown = (new BlockFactory)->fromArray($payload);
    expect($unknown)->toBeInstanceOf(UnknownBlock::class);
});
