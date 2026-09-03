<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\Exceptions\MembergyException;

it('builds the correct base URL with api version', function () {
    $connector = new MembergyConnector(
        baseUrl: 'https://members.example.org',
        tenant: 'my-club',
    );

    expect($connector->resolveBaseUrl())
        ->toBe('https://members.example.org/api/v1');
});

it('strips trailing slashes from the base URL', function () {
    $connector = new MembergyConnector(
        baseUrl: 'https://members.example.org/',
        tenant: 'my-club',
    );

    expect($connector->resolveBaseUrl())
        ->toBe('https://members.example.org/api/v1');
});

it('throws when the tenant slug is empty', function () {
    new MembergyConnector(
        baseUrl: 'https://members.example.org',
        tenant: '',
    );
})->throws(MembergyException::class);

it('returns a new instance when switching user tokens and keeps originals untouched', function () {
    $connector = new MembergyConnector(
        baseUrl: 'https://members.example.org',
        tenant: 'my-club',
    );

    $authed = $connector->withUserToken('abc123');

    expect($connector->hasUserToken())->toBeFalse()
        ->and($authed->hasUserToken())->toBeTrue()
        ->and($authed)->not->toBe($connector);
});
