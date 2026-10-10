<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Tests\Support;

use FilesystemIterator;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class RegistrationFixtureSync
{
    public static function assertSynchronized(string $sdkFixtures, string $backendRoot): void
    {
        $source = $backendRoot.'/tests/Fixtures/Contracts/Cms/v1/registration';
        Assert::assertDirectoryExists($source);
        $expected = self::paths($source);
        Assert::assertCount(43, $expected, 'The registration contract contains 43 response files.');
        Assert::assertSame($expected, self::paths($sdkFixtures.'/responses'), 'Registration response paths must match with no missing or extra files.');
        $wireNames = [];
        foreach ($expected as $relative) {
            Assert::assertSame(file_get_contents($source.'/'.$relative), file_get_contents($sdkFixtures.'/responses/'.$relative), "Registration response '{$relative}' must be byte-identical.");
            $wireName = pathinfo($relative, PATHINFO_FILENAME).'.json';
            $wireNames[] = $wireName;
            $wireSource = $backendRoot.'/tests/Fixtures/Registrations/Contracts/'.$wireName;
            Assert::assertFileExists($wireSource, 'Registration wire metadata must exist.');
            Assert::assertSame(file_get_contents($wireSource), file_get_contents($sdkFixtures.'/wire/'.$wireName), "Registration wire metadata '{$wireName}' must be byte-identical.");
        }
        sort($wireNames);
        Assert::assertSame($wireNames, self::paths($sdkFixtures.'/wire'), 'SDK registration wire paths must match with no missing or extra files.');
        $sdkPaths = [
            ...array_map(static fn (string $path): string => 'responses/'.$path, $expected),
            ...array_map(static fn (string $path): string => 'wire/'.$path, $wireNames),
            'registration.schema.json', 'registration.openapi.json', 'provenance.json',
        ];
        sort($sdkPaths);
        Assert::assertSame($sdkPaths, self::paths($sdkFixtures), 'SDK registration fixture paths must match with no missing or extra files.');
        foreach ([
            'registration.schema.json' => '/docs/features/website-cms/contracts/v1/schemas/registration.schema.json',
            'registration.openapi.json' => '/docs/features/website-cms/contracts/v1/registration.openapi.json',
            'provenance.json' => '/tests/Fixtures/Registrations/Contracts/provenance.json',
        ] as $name => $path) {
            Assert::assertFileExists($backendRoot.$path);
            Assert::assertSame(file_get_contents($backendRoot.$path), file_get_contents($sdkFixtures.'/'.$name), "Registration contract file '{$name}' must be byte-identical.");
        }
    }

    /** @return list<string> */
    private static function paths(string $root): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $files[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        sort($files);

        return $files;
    }
}
