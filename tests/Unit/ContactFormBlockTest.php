<?php

declare(strict_types=1);

use Lacodix\MembergySdk\DataObjects\Blocks\BlockSettings;
use Lacodix\MembergySdk\DataObjects\Blocks\ContactFormBlock;
use Lacodix\MembergySdk\Factories\BlockFactory;

it('hydrates the committed nullable native UUID reference', function (?string $uuid) {
    $block = (new BlockFactory)->fromArray([
        'id' => 'contact', 'type' => 'contact_form', 'version' => 1,
        'data' => ['form_uuid' => $uuid, 'future_data' => ['retained' => true]], 'settings' => [], 'future_block' => true,
    ]);
    assert($block instanceof ContactFormBlock);
    expect($block)->toBeInstanceOf(ContactFormBlock::class)
        ->and($block->formUuid)->toBe($uuid)->and($block->formHandle)->toBe('')->and($block->topics)->toBe([])
        ->and($block->definitionVersion)->toBeNull()->and($block->submitLabel)->toBeNull()->and($block->title)->toBeNull()
        ->and($block->dataExtra)->toBe(['future_data' => ['retained' => true]])->and($block->extra)->toBe(['future_block' => true]);
})->with([null, '11111111-1111-4111-8111-111111111111']);

it('hydrates requested optional native presentation properties without losing unknown data', function () {
    $block = (new BlockFactory)->fromArray([
        'id' => 'contact', 'type' => 'contact_form', 'version' => 1, 'settings' => [],
        'data' => ['form_uuid' => '11111111-1111-4111-8111-111111111111', 'definition_version' => 2, 'submit_label' => 'Send', 'title' => 'Contact us', 'future' => 'kept'],
    ]);
    assert($block instanceof ContactFormBlock);
    expect($block->definitionVersion)->toBe(2)->and($block->submitLabel)->toBe('Send')->and($block->title)->toBe('Contact us')
        ->and($block->dataExtra)->toBe(['future' => 'kept']);
});

it('retains legacy hydration and original positional and named constructor compatibility', function () {
    $legacy = (new BlockFactory)->fromArray([
        'id' => 'legacy', 'type' => 'contact_form', 'version' => 1, 'settings' => [],
        'data' => ['form_handle' => 'old-contact', 'topics' => [['key' => 'other', 'label' => 'Other']], 'old_extra' => true],
    ]);
    $positional = new ContactFormBlock('legacy', 1, BlockSettings::fromArray([]), 'old-contact', [], ['old_extra' => true], ['outer' => true]);
    $named = new ContactFormBlock(id: 'legacy', version: 1, settings: BlockSettings::fromArray([]), formHandle: 'old-contact', topics: [], dataExtra: ['old_extra' => true], extra: ['outer' => true]);
    assert($legacy instanceof ContactFormBlock);
    expect($legacy->formHandle)->toBe('old-contact')->and($legacy->topics[0]->key)->toBe('other')
        ->and($legacy->topics[0]->label)->toBe('Other')->and($legacy->formUuid)->toBeNull()
        ->and($legacy->dataExtra)->toBe(['old_extra' => true])
        ->and($positional)->toEqual($named)->and($positional->formUuid)->toBeNull();
});
