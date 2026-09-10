<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;

it('keeps the copied SDK fixtures byte-identical to the backend source of truth', function () {
    $backendRoot = getenv('MEMBERGY_BACKEND_ROOT');

    if (! is_string($backendRoot) || $backendRoot === '') {
        $sdkRoot = dirname(__DIR__, 2);
        $candidates = [
            dirname($sdkRoot, 3),
            dirname($sdkRoot).'/membergy',
        ];

        $backendRoot = collect($candidates)->first(
            static fn (string $candidate): bool => is_file(
                $candidate.'/docs/features/website-cms/contracts/v1/block-registry.json',
            ),
        );
    }

    if (! is_string($backendRoot) || $backendRoot === '') {
        if (getenv('CI') !== false) {
            throw new RuntimeException(
                'Set MEMBERGY_BACKEND_ROOT so CI executes the backend-to-SDK fixture gate.',
            );
        }

        Assert::markTestSkipped('No Membergy backend checkout was found for the fixture gate.');
    }

    $localRoot = dirname(contractFixturePath('block-document.json'));
    $backendFixtures = rtrim($backendRoot, '/').'/tests/Fixtures/Contracts/Cms/v1';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $backendFixtures,
        FilesystemIterator::SKIP_DOTS,
    ));
    $fixtureCount = 0;

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($backendFixtures) + 1);
        $local = $localRoot.'/'.$relative;

        expect($local)->toBeFile();
        expect(file_get_contents($local))->toBe(file_get_contents($file->getPathname()));
        $fixtureCount++;
    }

    $backendRegistry = rtrim($backendRoot, '/').'/docs/features/website-cms/contracts/v1/block-registry.json';
    expect(file_get_contents(contractFixturePath('block-registry.json')))
        ->toBe(file_get_contents($backendRegistry));
    expect($fixtureCount)->toBe(50);
});
