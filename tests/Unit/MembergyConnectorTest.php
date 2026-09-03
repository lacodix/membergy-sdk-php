<?php

declare(strict_types=1);

use GuzzleHttp\RequestOptions;
use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\Exceptions\MembergyException;
use Lacodix\MembergySdk\Requests\Content\ShowPageRequest;

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

it('applies explicit connection and request timeouts to Saloon pending requests', function () {
    $connector = new MembergyConnector(
        baseUrl: 'https://members.example.org',
        tenant: 'my-club',
        connectTimeout: 2.5,
        requestTimeout: 9.5,
    );
    $pending = $connector->createPendingRequest(new ShowPageRequest('my-club', 'welcome'));

    expect($pending->config()->get(RequestOptions::CONNECT_TIMEOUT))->toBe(2.5)
        ->and($pending->config()->get(RequestOptions::TIMEOUT))->toBe(9.5);
});
