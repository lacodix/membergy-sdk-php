<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Tests\Support\ContractFixtureSync;
use PHPUnit\Framework\AssertionFailedError;

it('keeps all 49 SDK response fixtures and the separate BlockRegistry byte-identical to the backend source of truth', function () {
    $backendRoot = getenv('MEMBERGY_BACKEND_ROOT');

    if (! is_string($backendRoot) || $backendRoot === '') {
        throw new RuntimeException('Set MEMBERGY_BACKEND_ROOT to the authoritative backend checkout; the fixture gate cannot be skipped.');
    }

    $backendRoot = rtrim($backendRoot, '/');
    ContractFixtureSync::assertSynchronized(
        dirname(contractFixturePath('block-document.json')),
        $backendRoot.'/tests/Fixtures/Contracts/Cms/v1',
        $backendRoot.'/docs/features/website-cms/contracts/v1/block-registry.json',
    );
});

it('rejects contract fixture drift in either repository', function (string $drift, string $message) {
    // Mutate private copies of existing SDK fixtures, never either repository's contract source.
    $root = sys_get_temp_dir().'/membergy-fixture-sync-'.bin2hex(random_bytes(8));
    $sdk = $root.'/sdk';
    $backend = $root.'/backend';
    $registry = $root.'/block-registry.json';
    $source = dirname(contractFixturePath('block-document.json'));
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

    try {
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($source) + 1);
            foreach ($relative === 'block-registry.json' ? [$sdk] : [$sdk, $backend] as $destination) {
                if (! is_dir(dirname($destination.'/'.$relative))) {
                    mkdir(dirname($destination.'/'.$relative), 0700, true);
                }
                copy($file->getPathname(), $destination.'/'.$relative);
            }
        }
        copy($sdk.'/block-registry.json', $registry);
        ContractFixtureSync::assertSynchronized($sdk, $backend, $registry);

        match ($drift) {
            'sdk missing' => unlink($sdk.'/pages/show.json'),
            'sdk extra' => copy($sdk.'/pages/show.json', $sdk.'/obsolete.json'),
            'sdk renamed' => rename($sdk.'/pages/show.json', $sdk.'/pages/obsolete.json'),
            'backend missing' => unlink($backend.'/pages/show.json'),
            'backend extra' => copy($backend.'/pages/show.json', $backend.'/obsolete.json'),
            'response bytes' => file_put_contents($sdk.'/pages/show.json', "\n", FILE_APPEND),
            'registry bytes' => file_put_contents($sdk.'/block-registry.json', "\n", FILE_APPEND),
            'sdk registry missing' => unlink($sdk.'/block-registry.json'),
            'backend registry missing' => unlink($registry),
            'backend directory missing' => $backend = $root.'/missing',
            default => throw new InvalidArgumentException("Unknown drift '{$drift}'."),
        };

        expect(fn () => ContractFixtureSync::assertSynchronized($sdk, $backend, $registry))
            ->toThrow(AssertionFailedError::class, $message);
    } finally {
        if (is_dir($root)) {
            $cleanup = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($cleanup as $file) {
                if ($file instanceof SplFileInfo) {
                    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }
            }
            rmdir($root);
        }
    }
})->with([
    'missing SDK response' => ['sdk missing', 'no missing or extra files'],
    'additional SDK response' => ['sdk extra', 'no missing or extra files'],
    'same count but different SDK paths' => ['sdk renamed', 'no missing or extra files'],
    'missing backend response' => ['backend missing', '49 response fixtures'],
    'additional backend response' => ['backend extra', '49 response fixtures'],
    'changed response bytes' => ['response bytes', "Response fixture 'pages/show.json' must be byte-identical"],
    'changed registry bytes' => ['registry bytes', 'BlockRegistry must be byte-identical'],
    'missing SDK registry' => ['sdk registry missing', 'no missing or extra files'],
    'missing backend registry' => ['backend registry missing', 'backend BlockRegistry must exist'],
    'missing backend checkout' => ['backend directory missing', 'contract fixture directory must exist'],
]);
