<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Tests\Support\ContractFixtureSync;
use Lacodix\MembergySdk\Tests\Support\RegistrationFixtures;
use Lacodix\MembergySdk\Tests\Support\RegistrationFixtureSync;
use PHPUnit\Framework\AssertionFailedError;

it('keeps the separate provisional registration-v1 fixtures, schema and provenance byte-identical to their backend source', function () {
    $backend = getenv('MEMBERGY_REGISTRATION_BACKEND_ROOT');
    if (! is_string($backend) || $backend === '') {
        throw new RuntimeException('Set MEMBERGY_REGISTRATION_BACKEND_ROOT to execute the separate registration-v1 fixture gate.');
    }
    RegistrationFixtureSync::assertSynchronized(RegistrationFixtures::root(), rtrim($backend, '/'));
});

/** @param callable(string, string, string): void $assert */
function withTemporaryCombinedFixtures(callable $assert): void
{
    // Private copies and synthetic drift only; this does not capture or construct real HTTP responses.
    $root = sys_get_temp_dir().'/membergy-combined-fixtures-'.bin2hex(random_bytes(8));
    $sdkCms = $root.'/sdk-cms';
    $sdkRegistration = $root.'/sdk-registration';
    $backend = $root.'/backend';
    $copy = static function (string $source, string $destination): void {
        if (! is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0700, true);
        }
        copy($source, $destination);
    };

    try {
        $cmsSource = dirname(contractFixturePath('block-document.json'));
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cmsSource, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($cmsSource) + 1);
            $copy($file->getPathname(), $sdkCms.'/'.$relative);
            $copy($file->getPathname(), $backend.($relative === 'block-registry.json'
                ? '/docs/features/website-cms/contracts/v1/block-registry.json'
                : '/tests/Fixtures/Contracts/Cms/v1/'.$relative));
        }
        $registrationSource = RegistrationFixtures::root();
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($registrationSource, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($registrationSource) + 1);
            $copy($file->getPathname(), $sdkRegistration.'/'.$relative);
            $destination = match (true) {
                str_starts_with($relative, 'responses/') => '/tests/Fixtures/Contracts/Cms/v1/registration/'.substr($relative, strlen('responses/')),
                str_starts_with($relative, 'wire/') => '/tests/Fixtures/Registrations/Contracts/'.substr($relative, strlen('wire/')),
                $relative === 'registration.schema.json' => '/docs/features/website-cms/contracts/v1/schemas/registration.schema.json',
                $relative === 'registration.openapi.json' => '/docs/features/website-cms/contracts/v1/registration.openapi.json',
                $relative === 'provenance.json' => '/tests/Fixtures/Registrations/Contracts/provenance.json',
                default => throw new RuntimeException('Unexpected source registration fixture path.'),
            };
            $copy($file->getPathname(), $backend.$destination);
        }
        $assert($sdkCms, $sdkRegistration, $backend);
    } finally {
        if (is_dir($root)) {
            $cleanup = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($cleanup as $file) {
                if ($file instanceof SplFileInfo) {
                    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
                }
            }
            rmdir($root);
        }
    }
}

it('accepts a private combined backend tree while preserving the separate SDK fixture namespaces', function () {
    withTemporaryCombinedFixtures(function (string $sdkCms, string $sdkRegistration, string $backend): void {
        ContractFixtureSync::assertSynchronized($sdkCms, $backend.'/tests/Fixtures/Contracts/Cms/v1', $backend.'/docs/features/website-cms/contracts/v1/block-registry.json');
        RegistrationFixtureSync::assertSynchronized($sdkRegistration, $backend);
    });
});

it('rejects drift in private combined fixture trees without hiding registration failures in the CMS gate', function (string $family, string $side, string $path, string $mutation, string $message) {
    withTemporaryCombinedFixtures(function (string $sdkCms, string $sdkRegistration, string $backend) use ($family, $side, $path, $mutation, $message): void {
        $backendCms = $backend.'/tests/Fixtures/Contracts/Cms/v1';
        $registry = $backend.'/docs/features/website-cms/contracts/v1/block-registry.json';
        ContractFixtureSync::assertSynchronized($sdkCms, $backendCms, $registry);
        RegistrationFixtureSync::assertSynchronized($sdkRegistration, $backend);
        $base = $side === 'backend' ? $backend : ($family === 'cms' ? $sdkCms : $sdkRegistration);
        $target = $base.'/'.$path;
        switch ($mutation) {
            case 'extra':
                if (! is_dir(dirname($target))) {
                    mkdir(dirname($target), 0700, true);
                }
                copy($family === 'cms' ? $sdkCms.'/pages/show.json' : $sdkRegistration.'/responses/offer-guest.json', $target);
                break;
            case 'missing':
                unlink($target);
                break;
            case 'renamed':
                rename($target, dirname($target).'/obsolete.json');
                break;
            case 'bytes':
                file_put_contents($target, "\n", FILE_APPEND);
                break;
            default:
                throw new InvalidArgumentException('Unknown synthetic fixture drift.');
        }
        if ($family === 'cms') {
            expect(fn () => ContractFixtureSync::assertSynchronized($sdkCms, $backendCms, $registry))->toThrow(AssertionFailedError::class, $message);
            RegistrationFixtureSync::assertSynchronized($sdkRegistration, $backend);
        } else {
            // Namespace exclusion is safe only because the separate registration assertion still fails.
            ContractFixtureSync::assertSynchronized($sdkCms, $backendCms, $registry);
            expect(fn () => RegistrationFixtureSync::assertSynchronized($sdkRegistration, $backend))->toThrow(AssertionFailedError::class, $message);
        }
    });
})->with([
    'unknown backend CMS path' => ['cms', 'backend', 'tests/Fixtures/Contracts/Cms/v1/obsolete.json', 'extra', '49 response fixtures'],
    'backend registration prefix lookalike' => ['cms', 'backend', 'tests/Fixtures/Contracts/Cms/v1/registration.json', 'extra', '49 response fixtures'],
    'registration subtree in SDK CMS' => ['cms', 'sdk', 'registration/obsolete.json', 'extra', 'no missing or extra files'],
    'missing SDK registration response' => ['registration', 'sdk', 'responses/offer-guest.json', 'missing', 'Registration response paths must match'],
    'extra SDK registration response' => ['registration', 'sdk', 'responses/obsolete.json', 'extra', 'Registration response paths must match'],
    'missing backend registration response' => ['registration', 'backend', 'tests/Fixtures/Contracts/Cms/v1/registration/offer-guest.json', 'missing', '41 response files'],
    'extra backend registration response' => ['registration', 'backend', 'tests/Fixtures/Contracts/Cms/v1/registration/obsolete.json', 'extra', '41 response files'],
    'same count different registration paths' => ['registration', 'backend', 'tests/Fixtures/Contracts/Cms/v1/registration/offer-guest.json', 'renamed', 'Registration response paths must match'],
    'changed binary document bytes' => ['registration', 'backend', 'tests/Fixtures/Contracts/Cms/v1/registration/document.pdf', 'bytes', "Registration response 'document.pdf' must be byte-identical"],
    'extra SDK wire metadata' => ['registration', 'sdk', 'wire/obsolete.json', 'extra', 'SDK registration wire paths must match'],
    'missing backend wire metadata' => ['registration', 'backend', 'tests/Fixtures/Registrations/Contracts/offer-guest.json', 'missing', 'Registration wire metadata must exist'],
    'changed backend schema bytes' => ['registration', 'backend', 'docs/features/website-cms/contracts/v1/schemas/registration.schema.json', 'bytes', "Registration contract file 'registration.schema.json' must be byte-identical"],
    'changed backend OpenAPI bytes' => ['registration', 'backend', 'docs/features/website-cms/contracts/v1/registration.openapi.json', 'bytes', "Registration contract file 'registration.openapi.json' must be byte-identical"],
    'changed backend provenance bytes' => ['registration', 'backend', 'tests/Fixtures/Registrations/Contracts/provenance.json', 'bytes', "Registration contract file 'provenance.json' must be byte-identical"],
]);
