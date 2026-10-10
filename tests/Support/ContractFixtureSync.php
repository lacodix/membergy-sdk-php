<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Tests\Support;

use FilesystemIterator;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ContractFixtureSync
{
    public static function assertSynchronized(string $sdkFixtures, string $backendFixtures, string $backendRegistry): void
    {
        // Only the backend may share its root with the independently gated registration family.
        // Keep paths() unfiltered so any extra SDK CMS path, including registration/, still fails.
        $backendPaths = array_values(array_filter(
            self::paths($backendFixtures),
            static fn (string $path): bool => ! str_starts_with($path, 'registration/'),
        ));
        Assert::assertCount(58, $backendPaths, 'The frozen backend contract contains 58 response fixtures.');

        $expectedSdkPaths = [...$backendPaths, 'block-registry.json'];
        sort($expectedSdkPaths);
        Assert::assertSame(
            $expectedSdkPaths,
            self::paths($sdkFixtures),
            'SDK fixture paths must match the backend responses plus the separate BlockRegistry (no missing or extra files).',
        );

        foreach ($backendPaths as $relative) {
            Assert::assertSame(
                file_get_contents($backendFixtures.'/'.$relative),
                file_get_contents($sdkFixtures.'/'.$relative),
                "Response fixture '{$relative}' must be byte-identical.",
            );
        }

        Assert::assertFileExists($backendRegistry, 'The backend BlockRegistry must exist.');
        Assert::assertSame(
            file_get_contents($backendRegistry),
            file_get_contents($sdkFixtures.'/block-registry.json'),
            'BlockRegistry must be byte-identical.',
        );
    }

    /** @return list<string> */
    private static function paths(string $root): array
    {
        Assert::assertDirectoryExists($root, 'The contract fixture directory must exist.');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        $paths = [];

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $paths[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        sort($paths);

        return $paths;
    }
}
