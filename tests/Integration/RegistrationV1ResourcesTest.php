<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Cache\CacheOptions;
use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\RegistrationApplication;
use Lacodix\MembergySdk\DataObjects\RegistrationOffer;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationResume;
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationSubmission;
use Lacodix\MembergySdk\Exceptions\RegistrationAuthenticationException;
use Lacodix\MembergySdk\Exceptions\RegistrationConflictException;
use Lacodix\MembergySdk\Exceptions\RegistrationException;
use Lacodix\MembergySdk\Exceptions\RegistrationExpiredException;
use Lacodix\MembergySdk\Exceptions\RegistrationIdentityException;
use Lacodix\MembergySdk\Exceptions\RegistrationNotFoundException;
use Lacodix\MembergySdk\Exceptions\RegistrationPayloadException;
use Lacodix\MembergySdk\Exceptions\RegistrationRateLimitException;
use Lacodix\MembergySdk\Exceptions\RegistrationTransportException;
use Lacodix\MembergySdk\Exceptions\RegistrationValidationException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Registrations\AbstractRegistrationRequest;
use Lacodix\MembergySdk\Requests\Registrations\DownloadRegistrationDocumentRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationOfferRequest;
use Lacodix\MembergySdk\Requests\Registrations\SubmitRegistrationApplicationRequest;
use Lacodix\MembergySdk\Support\Data;
use Lacodix\MembergySdk\Tests\Support\ForbiddenCache;
use Lacodix\MembergySdk\Tests\Support\RegistrationFixtures as Fixtures;
use Saloon\Contracts\Body\HasBody;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

function registrationV1Client(MockClient $mock, string $tenant = 'contract-club'): MembergyClient
{
    $connector = new MembergyConnector('https://sdk.membergy.test', $tenant, retryInterval: 0);
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

function assertRegistrationWire(MockClient $mock, string $name): void
{
    $wire = Fixtures::wire($name);
    $mock->assertSent(function (Request $request, Response $response) use ($wire): bool {
        $pending = $response->getPendingRequest();
        if ($request->getMethod()->value !== $wire['method'] || '/api/v1'.$request->resolveEndpoint() !== $wire['path'] || $pending->query()->all() !== []) {
            return false;
        }
        foreach (Data::object($wire, 'request_headers') as $name => $value) {
            if ($pending->headers()->get($name) !== $value) {
                return false;
            }
        }
        if ($request instanceof HasBody && $request->body()->all() !== Data::object($wire, 'request')) {
            return false;
        }

        return $request instanceof AbstractRegistrationRequest && $request->tries === 1 && $pending->config()->get('allow_redirects') === false;
    });
}

it('hydrates the real guest and account offer shapes without inventing form or identity fields', function (string $fixture, bool $account, bool $resumed) {
    $mock = new MockClient([Fixtures::response($fixture)]);
    $original = registrationV1Client($mock);
    $client = $account ? $original->withUserToken('synthetic-user-credential') : $original;
    $offer = $client->registrationOffers()->find('membership', $resumed ? Fixtures::resume() : null);
    $data = Data::object(Fixtures::payload($fixture), 'data');

    expect($offer)->toBeInstanceOf(RegistrationOffer::class)
        ->and($offer->slug)->toBe('membership')
        ->and($offer->version->id)->toBe(70001)
        ->and($offer->version->validFrom->format('Y-m-d'))->toBe('2026-09-01')
        ->and($offer->submissionTicket)->toBe($data['submission_ticket'])
        ->and($offer->formSchema[4]->options)->toBe(['mr' => 'Herr', 'mrs' => 'Frau', 'company' => 'Firma', 'family' => 'Familie'])
        ->and($offer->options[0]->fees->primary[0]->amount)->toBe('120.00')
        ->and($offer->requirements[1]->documentUrl)->toBe($data['requirements'][1]['document_url'])
        ->and($offer->applicant?->email)->toBe($data['applicant']['email'] ?? null)
        ->and($offer->prefill?->attributes)->toBe($data['prefill']['attributes'] ?? null)
        ->and($offer->prefill?->lockedFields)->toBe($data['prefill']['locked_fields'] ?? null)
        ->and($original->connector()->hasUserToken())->toBeFalse();
    assertRegistrationWire($mock, $fixture);
})->with([
    ['offer-guest', false, false], ['offer-unverified-account', true, false],
    ['offer-account', true, false], ['offer-account-follow-up', true, false],
    ['offer-own-person', true, false], ['offer-resumed', false, true],
]);

it('submits exact captured people, participants, proofs, UUID keys and honeypot values', function (string $fixture, bool $account, bool $resumed) {
    $mock = new MockClient([Fixtures::response($fixture)]);
    $client = registrationV1Client($mock);
    if ($account) {
        $client = $client->withUserToken('synthetic-user-credential');
    }
    $application = $client->registrationApplications()->submit('membership', Fixtures::submission($fixture), $resumed ? Fixtures::resume() : null);
    expect($application)->toBeInstanceOf(RegistrationApplication::class)
        ->and($application->publicId)->toBe(Fixtures::payload($fixture)['data']['public_id'])
        ->and($application->status)->toBe(Fixtures::payload($fixture)['data']['status']);
    assertRegistrationWire($mock, $fixture);
    $mock->assertSentCount(1);
})->with([
    ['submitted-adult', false, false], ['submitted-child', false, false],
    ['submitted-account', true, false], ['submitted-account-follow-up', true, false],
    ['submitted-resumed', false, true], ['suppressed', false, false],
]);

it('returns the original receipt when the caller explicitly replays the same resumed submission', function () {
    $mock = new MockClient([Fixtures::response('submitted-resumed'), Fixtures::response('resumed-replay')]);
    $client = registrationV1Client($mock);
    $submission = Fixtures::submission('submitted-resumed');
    $resume = Fixtures::resume();
    $first = $client->registrationApplications()->submit('membership', $submission, $resume);
    $replay = $client->registrationApplications()->submit('membership', $submission, $resume);
    expect($first->publicId)->toBe($replay->publicId)->and($first->status)->toBe($replay->status);
    assertRegistrationWire($mock, 'resumed-replay');
    $mock->assertSentCount(2);
});

it('downloads exact protected PDF bytes on the configured host and tenant path', function () {
    $mock = new MockClient([Fixtures::response('document')]);
    $document = registrationV1Client($mock)->registrationOffers()->document('membership', 70002);
    expect($document->bytes)->toBe(Fixtures::bytes('document'))->and($document->contentType)->toBe('application/pdf');
    assertRegistrationWire($mock, 'document');
    $mock->assertSent(fn (DownloadRegistrationDocumentRequest $request, Response $response): bool => str_starts_with($response->getPendingRequest()->getUrl(), 'https://sdk.membergy.test/api/v1/tenant/contract-club/'));
});

it('requests neutral magic links and resends through the exact captured bodies', function () {
    $mock = new MockClient([Fixtures::response('magic-requested'), Fixtures::response('resend')]);
    $applications = registrationV1Client($mock)->registrationApplications();
    $message = $applications->requestMagicLink('anna.qa@example.test');
    $resend = $applications->resendVerification('20000000-0000-4000-8000-000000000001', '10000000-0000-4000-8000-000000000001', 'anna.qa@example.test');
    expect($message->message)->toBe(Fixtures::payload('magic-requested')['message'])
        ->and($resend->message)->toBe(Fixtures::payload('resend')['message']);
    assertRegistrationWire($mock, 'magic-requested');
    assertRegistrationWire($mock, 'resend');
    $mock->assertSentCount(2);
});

it('keeps verification GETs read-only and requires an explicit consuming verification POST', function () {
    $mock = new MockClient([Fixtures::response('verification-landing'), Fixtures::response('verification-landing'), Fixtures::response('verified'), Fixtures::response('verification-replay')]);
    $applications = registrationV1Client($mock)->registrationApplications();
    $token = str_repeat('b', 64);
    expect($applications->verificationLanding($token)->state)->toBe('usable')
        ->and($applications->verificationLanding($token)->state)->toBe('usable')
        ->and($applications->verify($token)->status)->toBe('verified')
        ->and(fn () => $applications->verify($token))->toThrow(RegistrationExpiredException::class);
    assertRegistrationWire($mock, 'verification-landing');
    assertRegistrationWire($mock, 'verified');
    $mock->assertSentCount(4);
});

it('exchanges a magic link only on POST and sends the returned purpose-bound context per subsequent request', function () {
    $mock = new MockClient([
        Fixtures::response('resume-landing'), Fixtures::response('resume-landing'), Fixtures::response('resume-exchange'),
        Fixtures::response('offer-resumed'), Fixtures::response('offer-guest'), Fixtures::response('submitted-resumed'), Fixtures::response('resume-replayed'),
    ]);
    $client = registrationV1Client($mock);
    $token = str_repeat('b', 64);
    expect($client->registrationApplications()->resumeLanding($token)->state)->toBe('usable')
        ->and($client->registrationApplications()->resumeLanding($token)->state)->toBe('usable');
    $resume = $client->registrationApplications()->resume($token);
    expect($resume)->toBeInstanceOf(RegistrationResume::class)->and($resume->tenantSlug)->toBe('contract-club')
        ->and($resume->expiresAt->format(DATE_ATOM))->toBe('2026-10-01T13:00:04+00:00');
    expect($client->registrationOffers()->find('membership', $resume)->applicant?->email)->toBe('anna.qa@example.test')
        ->and($client->registrationOffers()->find('membership')->applicant)->toBeNull()
        ->and($client->registrationApplications()->submit('membership', Fixtures::submission('submitted-resumed'), $resume)->status)->toBe('verified')
        ->and(fn () => $client->registrationApplications()->resume($token))->toThrow(RegistrationExpiredException::class)
        ->and($client->connector()->headers()->get(AbstractRegistrationRequest::RESUME_HEADER))->toBeNull();
    assertRegistrationWire($mock, 'resume-landing');
    assertRegistrationWire($mock, 'resume-exchange');
    assertRegistrationWire($mock, 'submitted-resumed');
    $mock->assertSent(fn (Request $request, Response $response): bool => $request instanceof ShowRegistrationOfferRequest
        && $response->getPendingRequest()->headers()->get(AbstractRegistrationRequest::RESUME_HEADER) === null);
    $mock->assertSentCount(7);
});

it('rejects a Bearer and resume context conflict before sending without changing either client', function () {
    $mock = new MockClient;
    $guest = registrationV1Client($mock);
    $account = $guest->withUserToken('synthetic-user-credential');
    expect(fn () => $account->registrationOffers()->find('membership', Fixtures::resume()))->toThrow(RegistrationIdentityException::class)
        ->and(fn () => $account->registrationApplications()->submit('membership', Fixtures::submission(), Fixtures::resume()))->toThrow(RegistrationIdentityException::class)
        ->and(fn () => $account->registrationApplications()->resume(str_repeat('b', 64)))->toThrow(RegistrationIdentityException::class)
        ->and($guest->connector()->hasUserToken())->toBeFalse()->and($account->connector()->hasUserToken())->toBeTrue();
    $mock->assertSentCount(0);
});

it('rejects foreign tenant, offer and stale version bindings before sending', function () {
    $mock = new MockClient;
    $client = registrationV1Client($mock);
    $resume = Fixtures::resume();
    expect(fn () => registrationV1Client($mock, 'other-club')->registrationOffers()->find('membership', $resume))->toThrow(RegistrationIdentityException::class)
        ->and(fn () => $client->registrationOffers()->find('foreign-offer', $resume))->toThrow(RegistrationIdentityException::class);
    $values = Data::object(Fixtures::wire('submitted-resumed'), 'request');
    $values['offer_version_id'] = 70002;
    expect(fn () => $client->registrationApplications()->submit('membership', RegistrationSubmission::fromArray($values), $resume))->toThrow(RegistrationIdentityException::class);
    $mock->assertSentCount(0);
});

it('never accepts resume credentials from shared connector headers or browser query parameters', function () {
    $mock = new MockClient;
    $client = registrationV1Client($mock);
    $client->connector()->headers()->add('x-membergy-registration-resume', Fixtures::resume()->headerValue());
    expect(fn () => $client->registrationOffers()->find('membership'))->toThrow(RegistrationIdentityException::class);
    $other = registrationV1Client($mock);
    $other->connector()->query()->add('resume_context', Fixtures::resume()->headerValue());
    expect(fn () => $other->registrationOffers()->find('membership'))->toThrow(RegistrationIdentityException::class);
    $mock->assertSentCount(0);
});

it('reads only the authenticated account application shape', function () {
    $mock = new MockClient([Fixtures::response('application-own')]);
    $application = registrationV1Client($mock)->withUserToken('synthetic-user-credential')->registrationApplications()->find('20000000-0000-4000-8000-000000000005');
    expect($application->participantCount)->toBe(1)->and($application->offerSlug)->toBe('membership')
        ->and($application->submittedAt?->format(DATE_ATOM))->toBe('2026-10-01T12:00:04+00:00');
    assertRegistrationWire($mock, 'application-own');
});

it('maps captured registration error statuses through a verification mock without leaking their bodies', function (string $fixture, string $exception) {
    $mock = new MockClient([Fixtures::response($fixture)]);
    $client = registrationV1Client($mock);
    $old = ini_set('zend.exception_ignore_args', '0');
    try {
        try {
            $client->registrationApplications()->verify(str_repeat('b', 64));
            throw new LogicException('Expected a registration error.');
        } catch (RegistrationException $error) {
            expect($error)->toBeInstanceOf($exception)->and($error->getPrevious())->toBeNull()
                ->and((string) $error)->not->toContain(str_repeat('b', 64))
                ->and((string) $error)->not->toContain('anna.qa@example.test')
                ->and($error->getMessage())->not->toBe(Fixtures::payload($fixture)['message']);
            if ($error instanceof RegistrationValidationException) {
                expect($error->fields)->toBe(['submission_key']);
            }
            if ($error instanceof RegistrationRateLimitException) {
                expect($error->retryAfterSeconds)->toBe(60);
            }
        }
    } finally {
        ini_set('zend.exception_ignore_args', $old === false ? '1' : $old);
    }
    $mock->assertSentCount(1);
})->with([
    ['tenant-missing', RegistrationNotFoundException::class], ['verification-unknown', RegistrationNotFoundException::class],
    ['conflict', RegistrationConflictException::class], ['context-expired', RegistrationExpiredException::class],
    ['context-revoked', RegistrationExpiredException::class], ['resume-expired', RegistrationExpiredException::class],
    ['validation', RegistrationValidationException::class], ['rate-limited', RegistrationRateLimitException::class],
    ['application-guest', RegistrationAuthenticationException::class], ['application-foreign-account', RegistrationNotFoundException::class],
    ['context-foreign', RegistrationNotFoundException::class], ['context-malformed', RegistrationNotFoundException::class],
    ['context-conflicting', RegistrationNotFoundException::class], ['document-foreign', RegistrationNotFoundException::class],
    ['document-missing-70001', RegistrationNotFoundException::class], ['document-missing-999999', RegistrationNotFoundException::class],
    ['server-error-debug', RegistrationException::class],
]);

it('does not retry a failed submit or expose the sender exception containing credentials', function () {
    $mock = new MockClient([Fixtures::response('server-error-debug'), Fixtures::response('submitted-adult')]);
    expect(fn () => registrationV1Client($mock)->registrationApplications()->submit('membership', Fixtures::submission()))->toThrow(RegistrationException::class);
    $mock->assertSentCount(1);
    $attempts = 0;
    $fatal = new MockClient([static function (PendingRequest $pending) use (&$attempts): MockResponse {
        $attempts++;
        throw new FatalRequestException(new RuntimeException('secret-token anna.qa@example.test'), $pending);
    }]);
    try {
        registrationV1Client($fatal)->registrationApplications()->resume(str_repeat('b', 64));
    } catch (RegistrationTransportException $error) {
        expect($error->getPrevious())->toBeNull()->and((string) $error)->not->toContain('secret-token', 'anna.qa@example.test', str_repeat('b', 64))
            ->and($attempts)->toBe(1);

        return;
    }
    throw new LogicException('Expected a safe transport error.');
});

it('does not accept the existing browser-session resume shape as a headless context', function () {
    $mock = new MockClient([Fixtures::response('resume-browser')]);
    expect(fn () => registrationV1Client($mock)->registrationApplications()->resume(str_repeat('b', 64)))->toThrow(RegistrationPayloadException::class);
});

it('never reads or writes private registration responses through configured SDK caches even with incorrect cache headers', function (string $kind, bool $account) {
    // Fault injection changes only cache headers on captured response bytes, not the canonical fixture.
    $fixture = match ($kind) {
        'offer' => $account ? 'offer-account' : 'offer-guest', 'document' => 'document', default => 'verification-landing'
    };
    $headers = Data::object(Fixtures::wire($fixture), 'response_headers');
    $headers['Cache-Control'] = $account ? 'private, max-age=3600' : 'public, max-age=3600';
    $mock = new MockClient([
        MockResponse::make(Fixtures::bytes($fixture), 200, $headers), MockResponse::make(Fixtures::bytes($fixture), 200, $headers),
    ]);
    $public = new ForbiddenCache;
    $member = new ForbiddenCache;
    $client = registrationV1Client($mock)->withCache($public, $member, new CacheOptions(defaultTtl: 3600));
    if ($account) {
        $client = $client->withUserToken('synthetic-user-credential');
    }
    for ($i = 0; $i < 2; $i++) {
        match ($kind) {
            'offer' => $client->registrationOffers()->find('membership'),
            'document' => $client->registrationOffers()->document('membership', 70002),
            default => $client->registrationApplications()->verificationLanding(str_repeat('b', 64)),
        };
    }
    $mock->assertSentCount(2);
    expect($public->reads)->toBe(0)->and($member->reads)->toBe(0)
        ->and($public->writes)->toBe(0)->and($member->writes)->toBe(0);
})->with([['offer', false], ['offer', true], ['document', false], ['document', true], ['token', false], ['token', true]]);

it('detaches rejected identity exceptions from credential-bearing Saloon construction traces', function () {
    $mock = new MockClient;
    $client = registrationV1Client($mock)->withUserToken('synthetic-user-credential');
    $old = ini_set('zend.exception_ignore_args', '0');
    try {
        try {
            $client->registrationOffers()->find('membership', Fixtures::resume());
            throw new LogicException('Expected identity rejection.');
        } catch (RegistrationIdentityException $error) {
            expect($error->getPrevious())->toBeNull();
            foreach ($error->getTrace() as $frame) {
                foreach ($frame['args'] ?? [] as $argument) {
                    expect($argument)->not->toBeInstanceOf(Request::class);
                    expect($argument)->not->toBeInstanceOf(PendingRequest::class);
                    expect($argument)->not->toBeInstanceOf(RegistrationResume::class);
                    expect($argument)->not->toBeInstanceOf(MembergyConnector::class);
                }
            }
        }
    } finally {
        ini_set('zend.exception_ignore_args', $old === false ? '1' : $old);
    }
    $mock->assertSentCount(0);
});

it('uses the existing credential-token identity path and keeps guest registration requests separate', function () {
    $mock = new MockClient([
        MockResponse::make(file_get_contents(contractFixturePath('auth/token.json')) ?: '', 200, ['Content-Type' => 'application/json']),
        Fixtures::response('offer-account'), Fixtures::response('submitted-account'), Fixtures::response('offer-guest'),
    ]);
    $guest = registrationV1Client($mock);
    $token = $guest->auth()->tokenFromCredentials('account.qa@example.test', 'test-password', 'Website');
    $account = $guest->withUserToken($token->accessToken);
    expect($account->registrationOffers()->find('membership')->applicant?->email)->toBe('account.qa@example.test')
        ->and($account->registrationApplications()->submit('membership', Fixtures::submission('submitted-account'))->status)->toBe('verified')
        ->and($guest->registrationOffers()->find('membership')->applicant)->toBeNull();
    $mock->assertSent(fn (Request $request, Response $response): bool => $request instanceof SubmitRegistrationApplicationRequest
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer '.$token->accessToken);
    $mock->assertSent(fn (Request $request, Response $response): bool => $request instanceof ShowRegistrationOfferRequest
        && $response->body() === Fixtures::bytes('offer-guest')
        && $response->getPendingRequest()->headers()->get('Authorization') === null);
    $mock->assertSentCount(4);
});

it('isolates two applicants using the same client and offer without persisting either resume context', function () {
    // Two independent SDK objects reuse the captured shape; changed opaque credentials are test inputs only.
    $data = Data::object(Fixtures::payload('resume-exchange'), 'data');
    $first = RegistrationResume::fromArray($data);
    $data['resume_context'] = str_repeat('c', 64);
    $second = RegistrationResume::fromArray($data);
    $mock = new MockClient([Fixtures::response('offer-resumed'), Fixtures::response('offer-resumed'), Fixtures::response('offer-guest')]);
    $client = registrationV1Client($mock);
    $client->registrationOffers()->find('membership', $first);
    $client->registrationOffers()->find('membership', $second);
    $client->registrationOffers()->find('membership');
    $responses = $mock->getRecordedResponses();
    $contexts = array_map(static fn (Response $response): mixed => $response->getPendingRequest()->headers()->get(AbstractRegistrationRequest::RESUME_HEADER), $responses);
    expect(array_values($contexts))->toBe([str_repeat('a', 64), str_repeat('c', 64), null]);
});

it('reports revoked and expired contexts from the actual offer request', function (string $fixture) {
    $mock = new MockClient([Fixtures::response($fixture)]);
    expect(fn () => registrationV1Client($mock)->registrationOffers()->find('membership', Fixtures::resume()))->toThrow(RegistrationExpiredException::class);
    assertRegistrationWire($mock, $fixture);
    $mock->assertSentCount(1);
})->with(['context-expired', 'context-revoked']);

it('never touches either configured cache during the complete headless resume flow', function () {
    $mock = new MockClient([
        Fixtures::response('resume-landing'), Fixtures::response('resume-exchange'),
        Fixtures::response('offer-resumed'), Fixtures::response('offer-resumed'),
        Fixtures::response('submitted-resumed'), Fixtures::response('document'),
    ]);
    $public = new ForbiddenCache;
    $member = new ForbiddenCache;
    $client = registrationV1Client($mock)->withCache($public, $member);
    $applications = $client->registrationApplications();
    expect($applications->resumeLanding(str_repeat('b', 64))->state)->toBe('usable');
    $resume = $applications->resume(str_repeat('b', 64));
    $client->registrationOffers()->find('membership', $resume);
    $client->registrationOffers()->find('membership', $resume);
    expect($applications->submit('membership', Fixtures::submission('submitted-resumed'), $resume)->status)->toBe('verified')
        ->and($client->registrationOffers()->document('membership', 70002)->bytes)->toBe(Fixtures::bytes('document'));
    $mock->assertSentCount(6);
    expect($public->reads)->toBe(0)->and($member->reads)->toBe(0)
        ->and($public->writes)->toBe(0)->and($member->writes)->toBe(0);
});

it('counts cache access even when the existing CMS middleware swallows a store failure', function () {
    $public = new ForbiddenCache;
    $mock = new MockClient([MockResponse::make(file_get_contents(contractFixturePath('pages/show.json')) ?: '', 200, ['Content-Type' => 'application/json'])]);
    $client = registrationV1Client($mock)->withCache($public);
    $client->content()->pages()->find('willkommen');
    expect($public->reads)->toBeGreaterThan(0);
    $mock->assertSentCount(1);
});

it('maps the captured protected document error at its actual download endpoint', function () {
    $mock = new MockClient([Fixtures::response('document-missing-70001')]);
    expect(fn () => registrationV1Client($mock)->registrationOffers()->document('membership', 70001))->toThrow(RegistrationNotFoundException::class);
    assertRegistrationWire($mock, 'document-missing-70001');
    $mock->assertSentCount(1);
});

it('maps the captured unauthenticated application read without sending a guest bearer', function () {
    $mock = new MockClient([Fixtures::response('application-guest')]);
    expect(fn () => registrationV1Client($mock)->registrationApplications()->find('20000000-0000-4000-8000-000000000005'))->toThrow(RegistrationAuthenticationException::class);
    assertRegistrationWire($mock, 'application-guest');
    $mock->assertSent(fn (Request $request, Response $response): bool => $response->getPendingRequest()->headers()->get('Authorization') === null);
    $mock->assertSentCount(1);
});

it('sends the captured conflict submission once and requires the caller to reconcile the offer', function () {
    $mock = new MockClient([Fixtures::response('conflict'), Fixtures::response('submitted-adult')]);
    expect(fn () => registrationV1Client($mock)->registrationApplications()->submit('membership', Fixtures::submission('conflict')))->toThrow(RegistrationConflictException::class);
    assertRegistrationWire($mock, 'conflict');
    $mock->assertSentCount(1);
});

it('maps the captured landing rate limit without retrying or consuming a token', function () {
    $mock = new MockClient([Fixtures::response('rate-limited'), Fixtures::response('verification-landing')]);
    expect(fn () => registrationV1Client($mock)->registrationApplications()->verificationLanding(str_repeat('0', 64)))->toThrow(RegistrationRateLimitException::class);
    assertRegistrationWire($mock, 'rate-limited');
    $mock->assertSentCount(1);
});
