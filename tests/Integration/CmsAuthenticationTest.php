<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Lacodix\MembergySdk\Cache\ResponseCache;
use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\ContactFormSubmission;
use Lacodix\MembergySdk\Exceptions\AuthenticationException;
use Lacodix\MembergySdk\Exceptions\CmsAuthenticationException;
use Lacodix\MembergySdk\Exceptions\HttpRequestException;
use Lacodix\MembergySdk\Exceptions\MembergyException;
use Lacodix\MembergySdk\Exceptions\RegistrationAuthenticationException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\TransportException;
use Lacodix\MembergySdk\Exceptions\UnsafeRequestException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Auth\CreateTokenRequest;
use Lacodix\MembergySdk\Requests\Auth\RequestPasswordResetLinkRequest;
use Lacodix\MembergySdk\Requests\Content\ShowContactFormRequest;
use Lacodix\MembergySdk\Requests\Content\ShowFileRequest;
use Lacodix\MembergySdk\Requests\Content\ShowImageRequest;
use Lacodix\MembergySdk\Requests\Content\ShowPageRequest;
use Lacodix\MembergySdk\Requests\Content\SubmitContactFormRequest;
use Lacodix\MembergySdk\Requests\Content\SubscribeNewsletterRequest;
use Lacodix\MembergySdk\Requests\Me\ShowPersonRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationOfferRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationVerificationRequest;
use Lacodix\MembergySdk\Tests\Support\ArrayCache;
use Lacodix\MembergySdk\Tests\Support\ForbiddenCache;
use Lacodix\MembergySdk\Tests\Support\MutableCacheClock;
use Psr\Http\Message\RequestInterface;
use Saloon\Config;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Senders\GuzzleSender;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

it('carries an independent CMS header on public and visitor clones', function () {
    $credential = hash('sha256', 'cms-auth-test-credential');
    $authorizations = [];
    $mock = new MockClient([
        '*' => function (PendingRequest $pending) use ($credential, &$authorizations): MockResponse {
            $psr = $pending->createPsrRequest();
            expect(hash_equals($credential, $psr->getHeaderLine('X-Membergy-Cms-Token')))->toBeTrue();
            $authorizations[] = $psr->getHeaderLine('Authorization');

            return MockResponse::make(contractFixture('pages/show.json'));
        },
    ]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: $credential);
    $connector->withMockClient($mock);
    $public = MembergyClient::fromConnector($connector);
    $member = $public->withUserToken('visitor-token');
    foreach ([$public, $member, $member->withoutUserToken(), $member] as $client) {
        $client->content()->pages()->find('willkommen');
    }
    expect($authorizations)->toBe(['', 'Bearer visitor-token', '', 'Bearer visitor-token']);
});

it('rejects a missing CMS credential before cache or mock transport', function (?string $credential) {
    $store = new ForbiddenCache;
    $mock = new MockClient;
    $client = new MembergyClient('https://api.membergy.test', 'demo', cmsToken: $credential, responseCache: new ResponseCache($store, $store));
    $client->connector()->withMockClient($mock);
    expect(fn () => $client->content()->pages()->find('willkommen'))->toThrow(CmsAuthenticationException::class);
    $mock->assertSentCount(0);
    expect($store->reads)->toBe(0)->and($store->writes)->toBe(0);
})->with([null, '', ' ', "invalid\ncredential"]);

it('rejects foreign endpoints and PSR rewrites before dispatch', function (bool $rewrite) {
    $request = new class($rewrite) extends Request
    {
        protected Method $method = Method::GET;

        public ?bool $allowBaseUrlOverride = true;

        public function __construct(private bool $rewrite) {}

        public function resolveEndpoint(): string
        {
            return $this->rewrite ? '/tenant/demo/content/pages' : 'https://foreign.invalid/media';
        }

        public function handlePsrRequest(RequestInterface $request, PendingRequest $pendingRequest): RequestInterface
        {
            return $this->rewrite ? $request->withUri(new Uri('https://foreign.invalid/media')) : $request;
        }
    };
    $mock = new MockClient(['*' => MockResponse::make([])]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: hash('sha256', 'cms-auth-test-credential'));
    $connector->withMockClient($mock);
    expect(fn () => $connector->send($request))->toThrow(UnsafeRequestException::class);
    $mock->assertSentCount(0);
})->with([false, true]);

it('suppresses all ordinary SDK debug callbacks and keeps dumps and JSON credential free', function (bool $requestHooks) {
    $credential = hash('sha256', 'cms-auth-test-credential');
    $client = new MembergyClient('https://api.membergy.test', 'demo', cmsToken: $credential);
    $client->connector()->withMockClient(new MockClient(['*' => MockResponse::make([])]));
    $request = new ShowPageRequest('demo', 'willkommen');
    $calls = 0;
    $owner = $requestHooks ? $request : $client->connector();
    $owner->debugRequest(static function () use (&$calls): void {
        $calls++;
    })
        ->debugResponse(static function () use (&$calls): void {
            $calls++;
        });
    $response = $client->connector()->send($request);
    ob_start();
    var_dump($client, $client->connector(), $response->getPendingRequest());
    $dump = (string) ob_get_clean();
    expect($calls)->toBe(0)
        ->and(str_contains($dump.(string) json_encode([$client, $client->connector(), $response]), $credential))->toBeFalse()
        ->and($response->getPsrRequest()->hasHeader('X-Membergy-Cms-Token'))->toBeFalse();
    expect(fn () => serialize($client))->toThrow(LogicException::class);
})->with([false, true]);

it('redacts a reflected CMS credential in typed errors without retaining vendor exceptions', function () {
    $credential = hash('sha256', 'cms-auth-test-credential');
    $client = new MembergyClient('https://api.membergy.test', 'demo', cmsToken: $credential);
    $client->connector()->withMockClient(new MockClient(['*' => MockResponse::make([
        'code' => 'cms_validation_failed', 'errors' => ['field' => ['Rejected '.$credential]],
    ], 422)]));
    try {
        $client->content()->pages()->find('willkommen');
    } catch (RequestValidationException $exception) {
        expect(str_contains((string) json_encode($exception->errors), $credential))->toBeFalse()
            ->and($exception->getPrevious())->toBeNull();

        return;
    }
    throw new RuntimeException('Expected typed validation failure.');
});

it('disables redirects even when a request opts in', function () {
    $mock = new MockClient(['*' => function (PendingRequest $pending): MockResponse {
        expect($pending->config()->get('allow_redirects'))->toBeFalse();

        return MockResponse::make([], 302, ['Location' => 'https://foreign.invalid']);
    }]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: hash('sha256', 'cms-auth-test-credential'));
    $connector->withMockClient($mock);
    $request = new ShowPageRequest('demo', 'willkommen');
    $request->config()->add('allow_redirects', true);
    $connector->send($request);
    $mock->assertSentCount(1);
});

it('partitions public and member response caches by CMS credential without storing it', function (bool $member) {
    $store = new ArrayCache;
    $keys = [hash('sha256', 'credential-a'), hash('sha256', 'credential-b')];
    $mock = new MockClient(['*' => MockResponse::make(contractFixture('pages/show.json'), headers: [
        'Content-Type' => 'application/json',
        'Cache-Control' => ($member ? 'private' : 'public').', max-age=300',
    ])]);
    foreach ($keys as $key) {
        $client = new MembergyClient('https://api.membergy.test', 'demo', cmsToken: $key);
        $client->connector()->withMockClient($mock);
        $client = $client->withCache($store, $store);
        if ($member) {
            $client = $client->withUserToken('same-visitor');
        }
        $client->content()->pages()->find('willkommen');
        $client->content()->pages()->find('willkommen');
    }
    $mock->assertSentCount(2);
    $snapshot = serialize($store->values);
    expect(str_contains($snapshot, $keys[0]) || str_contains($snapshot, $keys[1]))->toBeFalse();
})->with([false, true]);

it('reports missing CMS configuration clearly for registration too', function () {
    $client = new MembergyClient('https://api.membergy.test', 'demo');
    expect(fn () => $client->registrationOffers()->find('membership'))->toThrow(CmsAuthenticationException::class);
});

it('distinguishes rejected CMS credentials from visitor authentication without retaining private errors', function (string $operation, string $code) {
    $mock = new MockClient([MockResponse::make([
        'code' => $code, 'message' => testCmsToken(),
    ], 401, ['Cache-Control' => 'private, no-store', 'Content-Type' => 'application/json'])]);
    $store = new ArrayCache;
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', userToken: 'visitor-token', cmsToken: testCmsToken(), responseCache: new ResponseCache($store, $store));
    $connector->withMockClient($mock);
    $client = MembergyClient::fromConnector($connector);
    $expected = $code === 'cms_client_unauthenticated'
        ? CmsAuthenticationException::class
        : ($operation === 'registration' ? RegistrationAuthenticationException::class : AuthenticationException::class);
    $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
    try {
        try {
            match ($operation) {
                'page' => $client->content()->pages()->find('willkommen'),
                'contact submit' => $client->content()->forms()->submit('11111111-1111-4111-8111-111111111111', new ContactFormSubmission('opaque-ticket')),
                'registration' => $client->registrationOffers()->find('membership'),
                default => throw new LogicException('Unknown test operation.'),
            };
            throw new LogicException('Expected a typed authentication failure.');
        } catch (MembergyException $error) {
            expect($error::class)->toBe($expected)
                ->and($error->getPrevious())->toBeNull()
                ->and(method_exists($error, 'getResponse'))->toBeFalse()
                ->and(method_exists($error, 'getPendingRequest'))->toBeFalse()
                ->and(str_contains((string) $error, testCmsToken()))->toBeFalse()
                ->and(str_contains(json_encode($error, JSON_THROW_ON_ERROR), testCmsToken()))->toBeFalse();
            if ($code === 'cms_client_unauthenticated') {
                expect($error instanceof AuthenticationException)->toBeFalse()
                    ->and($error instanceof RegistrationAuthenticationException)->toBeFalse();
            }
        }
    } finally {
        ini_set('zend.exception_ignore_args', $ignoreArgs === false ? '1' : $ignoreArgs);
    }
    $mock->assertSentCount(1);
    expect($store->writes)->toBe(0);
})->with(['page', 'contact submit', 'registration'])->with(['cms_client_unauthenticated', 'cms_unauthenticated']);

it('requires the exact top-level CMS code and 401 status instead of inferring from messages', function (array|string $body, int $status, string $expected) {
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: testCmsToken());
    $mock = new MockClient([MockResponse::make($body, $status, ['Cache-Control' => 'private, no-store'])]);
    $connector->withMockClient($mock);
    expect(fn () => MembergyClient::fromConnector($connector)->content()->pages()->find('willkommen'))->toThrow($expected);
    $mock->assertSentCount(1);
})->with([
    'message only' => [['message' => 'cms_client_unauthenticated'], 401, AuthenticationException::class],
    'nested code' => [['error' => ['code' => 'cms_client_unauthenticated']], 401, AuthenticationException::class],
    'wrong case' => [['code' => 'CMS_CLIENT_UNAUTHENTICATED'], 401, AuthenticationException::class],
    'non-string code' => [['code' => ['cms_client_unauthenticated']], 401, AuthenticationException::class],
    'malformed JSON' => ['{"code":"cms_client_unauthenticated"', 401, AuthenticationException::class],
    'different status' => [['code' => 'cms_client_unauthenticated'], 403, HttpRequestException::class],
]);

it('detaches low level transport exceptions from credential-bearing previous requests', function (bool $async) {
    $credential = hash('sha256', 'cms-auth-test-credential');
    $failure = MockResponse::make()->throw(static fn (PendingRequest $pending): FatalRequestException => new FatalRequestException(
        new ConnectException('Synthetic connection failure.', $pending->createPsrRequest()), $pending,
    ));
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', tries: 1, cmsToken: $credential);
    $connector->withMockClient(new MockClient([$failure]));
    try {
        $request = new ShowPageRequest('demo', 'willkommen');
        $async ? $connector->sendAsync($request)->wait() : $connector->send($request);
    } catch (TransportException $exception) {
        expect($exception->getPrevious())->toBeNull()->and(str_contains((string) $exception, $credential))->toBeFalse();

        return;
    }
    throw new RuntimeException('Expected detached SDK transport failure.');
})->with([false, true]);

it('sends the CMS credential through each request family and connector clone', function (string $family) {
    $uuid = '11111111-1111-4111-8111-111111111111';
    $request = match ($family) {
        'content' => new ShowPageRequest('demo', 'willkommen'),
        'image metadata' => new ShowImageRequest('demo', $uuid),
        'file metadata' => new ShowFileRequest('demo', $uuid),
        'contact' => new ShowContactFormRequest('demo', $uuid),
        'contact submit' => new SubmitContactFormRequest('demo', $uuid, new ContactFormSubmission('opaque-ticket')),
        'newsletter' => new SubscribeNewsletterRequest('demo', [$uuid], 'visitor@example.test'),
        'auth' => new CreateTokenRequest('visitor@example.test', bin2hex(random_bytes(16)), 'Website'),
        'password' => new RequestPasswordResetLinkRequest('visitor@example.test'),
        'self service' => new ShowPersonRequest('demo'),
        'registration' => new ShowRegistrationOfferRequest('demo', 'membership'),
        'registration landing' => new ShowRegistrationVerificationRequest(str_repeat('b', 64)),
        default => throw new LogicException('Unknown SDK request family.'),
    };
    $mock = new MockClient(['*' => static function (PendingRequest $pending): MockResponse {
        expect(hash_equals(testCmsToken(), $pending->createPsrRequest()->getHeaderLine('X-Membergy-Cms-Token')))->toBeTrue();

        return MockResponse::make([]);
    }]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: testCmsToken());
    $connector->withMockClient($mock);
    foreach ([$connector, $connector->withUserToken('visitor')->withoutUserToken(), $connector->withStaticUserToken('server-user')->withoutUserToken(), $connector->withResponseCache(new ResponseCache(new ArrayCache))] as $copy) {
        MembergyClient::fromConnector($copy)->connector()->send($request);
    }
    $mock->assertSentCount(4);
})->with(['content', 'image metadata', 'file metadata', 'contact', 'contact submit', 'newsletter', 'auth', 'password', 'self service', 'registration', 'registration landing']);

it('retains CMS authentication during conditional revalidation and surfaces authoritative denials', function (int $status, string $code, string $expected) {
    $store = new ArrayCache;
    $clock = new MutableCacheClock;
    $mock = new MockClient([
        MockResponse::make(contractFixture('pages/show.json'), headers: ['Content-Type' => 'application/json', 'Cache-Control' => 'public, max-age=1, stale-if-error=300', 'ETag' => 'public-v1']),
        static function (PendingRequest $pending) use ($status, $code): MockResponse {
            expect($pending->headers()->get('If-None-Match'))->toBe('public-v1')
                ->and(hash_equals(testCmsToken(), $pending->createPsrRequest()->getHeaderLine('X-Membergy-Cms-Token')))->toBeTrue();

            return MockResponse::make(['code' => $code], $status, ['Cache-Control' => 'private, no-store']);
        },
    ]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: testCmsToken(), responseCache: new ResponseCache($store, clock: $clock));
    $connector->withMockClient($mock);
    $client = MembergyClient::fromConnector($connector);
    $client->content()->pages()->find('willkommen');
    $clock->advance(2);
    expect(fn () => $client->content()->pages()->find('willkommen'))->toThrow($expected);
    $mock->assertSentCount(2);
    expect($store->writes)->toBe(1);
})->with([
    [401, 'cms_client_unauthenticated', CmsAuthenticationException::class],
    [401, 'cms_unauthenticated', AuthenticationException::class],
    [403, 'cms_forbidden', HttpRequestException::class],
]);

it('keeps media URL factories credential free without requiring transport configuration', function () {
    $uuid = '11111111-1111-4111-8111-111111111111';
    $without = new MembergyClient('https://api.membergy.test', 'demo');
    $with = new MembergyClient('https://api.membergy.test', 'demo', cmsToken: testCmsToken());
    expect($with->image($uuid)->url())->toBe($without->image($uuid)->url())
        ->and(str_contains($with->image($uuid)->url(), testCmsToken()))->toBeFalse();
});

it('disables raw sender diagnostics and credential-bearing telemetry', function () {
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: testCmsToken());
    $request = new ShowPageRequest('demo', 'willkommen');
    $request->config()->merge(['debug' => true, 'on_stats' => static function (): void {}]);
    $connector->withMockClient(new MockClient(['*' => static function (PendingRequest $pending): MockResponse {
        $pending->createPsrRequest();
        expect($pending->config()->get('debug'))->toBeFalse()->and($pending->config()->get('on_stats'))->toBeNull();

        return MockResponse::make([]);
    }]));
    $connector->send($request);
});

it('rejects query-bearing or credential-bearing configured base URLs', function (string $url) {
    expect(fn () => new MembergyClient($url, 'demo', cmsToken: testCmsToken()))->toThrow(UnsafeRequestException::class);
})->with(['https://user:placeholder@api.membergy.test', 'https://api.membergy.test?key=placeholder', 'https://api.membergy.test#fragment']);

it('rejects credentials in request URLs before a cache lookup', function () {
    $store = new ForbiddenCache;
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: testCmsToken(), responseCache: new ResponseCache($store));
    $connector->withMockClient(new MockClient(['*' => MockResponse::make([])]));
    $request = new ShowPageRequest('demo', 'willkommen');
    $request->query()->add('credential', testCmsToken());
    expect(fn () => $connector->send($request))->toThrow(UnsafeRequestException::class);
    expect($store->reads)->toBe(0)->and($store->writes)->toBe(0);
});

it('sends the actual Guzzle request to the trusted origin once without following redirects', function () {
    $mockHandler = new MockHandler([
        new Response(302, ['Location' => 'https://foreign.invalid']),
        new Response(200),
    ]);
    $history = [];
    $sender = new GuzzleSender;
    $sender->getHandlerStack()->setHandler($mockHandler);
    $sender->addMiddleware(Middleware::history($history));
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: testCmsToken());
    (new ReflectionProperty(Connector::class, 'sender'))->setValue($connector, $sender);
    $request = new ShowPageRequest('demo', 'willkommen');
    $request->config()->add('allow_redirects', true);
    $response = $connector->send($request);
    expect($response->status())->toBe(302)->and($history)->toHaveCount(1)
        ->and(hash_equals(testCmsToken(), $history[0]['request']->getHeaderLine('X-Membergy-Cms-Token')))->toBeTrue()
        ->and($response->getPsrRequest()->hasHeader('X-Membergy-Cms-Token'))->toBeFalse();
});

it('carries CMS authentication on asynchronous calls and rejects missing credentials there too', function () {
    $mock = new MockClient(['*' => static function (PendingRequest $pending): MockResponse {
        expect(hash_equals(testCmsToken(), $pending->createPsrRequest()->getHeaderLine('X-Membergy-Cms-Token')))->toBeTrue();

        return MockResponse::make([]);
    }]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: testCmsToken());
    $connector->withMockClient($mock);
    $request = new ShowPageRequest('demo', 'willkommen');
    $response = $connector->sendAsync($request)->wait();
    expect($response->getPsrRequest()->hasHeader('X-Membergy-Cms-Token'))->toBeFalse();
    expect(fn () => (new MembergyConnector('https://api.membergy.test', 'demo'))->sendAsync($request)->wait())->toThrow(CmsAuthenticationException::class);
});

it('does not inherit an event-emitting global sender for CMS credentials', function () {
    Config::setSenderResolver(static function (): never {
        throw new LogicException('Global sender must not receive the CMS secret.');
    });
    try {
        $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: testCmsToken());
        expect($connector->sender())->toBeInstanceOf(GuzzleSender::class);
    } finally {
        Config::setSenderResolver(null);
    }
});

it('blocks an off-origin endpoint introduced by a retry hook', function () {
    $request = new class extends Request
    {
        protected Method $method = Method::GET;

        public ?bool $allowBaseUrlOverride = true;

        private string $endpoint = '/tenant/demo/content/pages';

        public function resolveEndpoint(): string
        {
            return $this->endpoint;
        }

        public function handleRetry(FatalRequestException|RequestException $exception, Request $request): bool
        {
            $this->endpoint = 'https://foreign.invalid';

            return true;
        }
    };
    $mock = new MockClient(['*' => MockResponse::make([], 503)]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', retryInterval: 0, cmsToken: testCmsToken());
    $connector->withMockClient($mock);
    expect(fn () => $connector->send($request))->toThrow(UnsafeRequestException::class);
    $mock->assertSentCount(1);
});

it('keeps CMS storage opaque to export and Symfony object inspection', function () {
    $client = new MembergyClient('https://api.membergy.test', 'demo', cmsToken: testCmsToken());
    $cloned = (new VarCloner)->cloneVar($client);
    $output = var_export($client, true).(new CliDumper)->dump($cloned, true);
    expect(str_contains($output, testCmsToken()))->toBeFalse();
});

it('rejects visitor identity overrides before they can reuse another member cache partition', function () {
    $store = new ForbiddenCache;
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', userToken: 'visitor-a', cmsToken: testCmsToken(), responseCache: new ResponseCache($store, $store));
    $connector->authenticate(new TokenAuthenticator('visitor-b'));
    $mock = new MockClient(['*' => MockResponse::make([])]);
    $connector->withMockClient($mock);
    expect(fn () => $connector->send(new ShowPageRequest('demo', 'willkommen')))->toThrow(UnsafeRequestException::class);
    expect($store->reads)->toBe(0)->and($store->writes)->toBe(0);
    $mock->assertSentCount(0);
});
