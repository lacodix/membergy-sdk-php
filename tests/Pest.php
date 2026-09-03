<?php

declare(strict_types=1);

// Shared Pest configuration. Individual tests opt in to the
// Integration suite by living under tests/Integration.

uses()->in('Unit', 'Integration');

function contractFixturePath(string $path): string
{
    return __DIR__.'/Fixtures/Contracts/Cms/v1/'.ltrim($path, '/');
}

/** @return array<string, mixed> */
function contractFixture(string $path): array
{
    $contents = file_get_contents(contractFixturePath($path));

    if ($contents === false) {
        throw new RuntimeException("Unable to read contract fixture '{$path}'.");
    }

    $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

    if (! is_array($decoded) || array_is_list($decoded)) {
        throw new RuntimeException("Contract fixture '{$path}' must contain a JSON object.");
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
}
