<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use Lacodix\MembergySdk\Cache\ResponseCache;
use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\ContactFormAcknowledgement;
use Lacodix\MembergySdk\DataObjects\ContactFormDefinition;
use Lacodix\MembergySdk\DataObjects\ContactFormField;
use Lacodix\MembergySdk\DataObjects\ContactFormSubmission;
use Lacodix\MembergySdk\Exceptions\AuthenticationException;
use Lacodix\MembergySdk\Exceptions\HttpRequestException;
use Lacodix\MembergySdk\Exceptions\HydrationException;
use Lacodix\MembergySdk\Exceptions\MembergyException;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Exceptions\TransportException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\ShowContactFormRequest;
use Lacodix\MembergySdk\Requests\Content\SubmitContactFormRequest;
use Lacodix\MembergySdk\Tests\Support\ForbiddenCache;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Http\Response;

function contactFormUuid(): string
{
    return '11111111-1111-4111-8111-111111111111';
}

/** Synthetic example of the native normalized FormSchema, not a backend capture.
 * @return array<string, mixed>
 */
function contactFormPayload(string $ticket = 'opaque-ticket'): array
{
    return ['data' => [
        'uuid' => contactFormUuid(), 'name' => 'Contact',
        'definition' => [
            'schema_version' => 1,
            'fields' => array_map(static fn (string $key, string $type): array => [
                'key' => $key, 'type' => $type, 'label' => ucfirst($key),
                'required' => $key !== 'contact_reason', 'enabled' => true,
                'options' => $key === 'contact_reason' ? ['Membership=membership', 'Other=other'] : [],
                'future_field' => true,
            ], ['email', 'subject', 'message', 'contact_reason'], ['email', 'text', 'textarea', 'select']),
            'messages' => ['success' => 'Thank you.', 'error' => 'Please try again.', 'future_message' => true],
            'future_schema' => true,
        ],
        'submission_ticket' => $ticket, 'minimum_seconds' => 3, 'expires_at' => '2026-11-01T12:15:00+00:00',
        'future_form' => true,
    ]];
}

function contactFormsClient(MockClient $mock, string $tenant = 'demo'): MembergyClient
{
    $connector = new MembergyConnector('https://api.membergy.test', $tenant, retryInterval: 0, cmsToken: testCmsToken());
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

it('fetches a native definition and fresh ticket as dedicated typed DTOs', function () {
    $mock = new MockClient([MockResponse::make(contactFormPayload())]);
    $form = contactFormsClient($mock, 'tenant / scoped')->content()->forms()->find(contactFormUuid());

    expect($form)->toBeInstanceOf(ContactFormDefinition::class)
        ->and($form->uuid)->toBe(contactFormUuid())->and($form->name)->toBe('Contact')
        ->and($form->submissionTicket)->toBe('opaque-ticket')->and($form->minimumSeconds)->toBe(3)
        ->and($form->expiresAt->format(DATE_ATOM))->toBe('2026-11-01T12:15:00+00:00')
        ->and($form->extra)->toBe(['future_form' => true])
        ->and($form->definition->schemaVersion)->toBe(1)
        ->and($form->definition->extra)->toBe(['future_schema' => true])
        ->and($form->definition->messages->success)->toBe('Thank you.')
        ->and($form->definition->messages->error)->toBe('Please try again.')
        ->and($form->definition->messages->extra)->toBe(['future_message' => true]);
    foreach ($form->definition->fields as $index => $field) {
        expect($field)->toBeInstanceOf(ContactFormField::class)
            ->and($field->key)->toBe(['email', 'subject', 'message', 'contact_reason'][$index])
            ->and($field->type)->toBe(['email', 'text', 'textarea', 'select'][$index])
            ->and($field->label)->toBe(ucfirst($field->key))
            ->and($field->required)->toBe($index < 3)->and($field->enabled)->toBeTrue()
            ->and($field->options)->toBe($index === 3 ? ['Membership=membership', 'Other=other'] : [])
            ->and($field->extra)->toBe(['future_field' => true]);
    }
    $mock->assertSent(function (Request $request, Response $response): bool {
        return $request->getMethod() === Method::GET
            && $request->resolveEndpoint() === '/tenant/tenant%20%2F%20scoped/content/forms/'.contactFormUuid()
            && $request->query()->all() === []
            && $response->getPendingRequest()->headers()->get('Authorization') === null;
    });
});

it('bypasses every cache access and revalidation when obtaining fresh tickets', function () {
    $store = new ForbiddenCache;
    $mock = new MockClient([
        MockResponse::make(contactFormPayload('first'), headers: ['Cache-Control' => 'public, max-age=300', 'ETag' => 'ticket']),
        MockResponse::make(contactFormPayload('second'), headers: ['Cache-Control' => 'private, no-store']),
    ]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', responseCache: new ResponseCache($store, $store), cmsToken: testCmsToken());
    $connector->withMockClient($mock);
    $client = MembergyClient::fromConnector($connector);
    expect($client->content()->forms()->find(contactFormUuid())->submissionTicket)->toBe('first')
        ->and($client->content()->forms()->find(contactFormUuid())->submissionTicket)->toBe('second')
        ->and($store->reads)->toBe(0)->and($store->writes)->toBe(0);
    $mock->assertSentCount(2);
    $mock->assertSent(function (Request $request, Response $response): bool {
        $headers = $response->getPendingRequest()->headers();

        return $headers->get('Cache-Control') === 'no-store'
            && $headers->get('If-None-Match') === null && $headers->get('If-Modified-Since') === null;
    });
});

it('submits exactly the known typed payload and parses a 202 acceptance without cache access', function () {
    $store = new ForbiddenCache;
    $mock = new MockClient([MockResponse::make(['data' => [
        'status' => 'accepted', 'message' => 'Your message has been accepted for sending.', 'future_ack' => true,
    ]], 202)]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', responseCache: new ResponseCache($store, $store), cmsToken: testCmsToken());
    $connector->withMockClient($mock);
    $submission = new ContactFormSubmission(
        submissionTicket: 'opaque-ticket', email: 'visitor@example.test', subject: 'Question',
        message: "Hello\r\nWorld\t!", contactReason: 'membership', website: '',
    );
    $ack = MembergyClient::fromConnector($connector)->content()->forms()->submit(contactFormUuid(), $submission);
    expect($ack)->toBeInstanceOf(ContactFormAcknowledgement::class)
        ->and($ack->status)->toBe('accepted')->and($ack->message)->toBe('Your message has been accepted for sending.')
        ->and($ack->extra)->toBe(['future_ack' => true])
        ->and($store->reads)->toBe(0)->and($store->writes)->toBe(0);
    $mock->assertSent(function (Request $request, Response $response): bool {
        return $request instanceof SubmitContactFormRequest
            && $request->getMethod() === Method::POST
            && $request->resolveEndpoint() === '/tenant/demo/content/forms/'.contactFormUuid().'/submissions'
            && $request->query()->all() === []
            && $request->body()->all() === [
                'submission_ticket' => 'opaque-ticket', 'email' => 'visitor@example.test', 'subject' => 'Question',
                'message' => "Hello\r\nWorld\t!", 'contact_reason' => 'membership', 'website' => '',
            ]
            && $response->getPendingRequest()->headers()->get('Content-Type') === 'application/json'
            && $response->getPendingRequest()->headers()->get('Cache-Control') === 'no-store';
    });
    $mock->assertSentCount(1);
});

it('rejects an invalid UUID without sending for either operation', function (string $uuid) {
    $mock = new MockClient;
    $forms = contactFormsClient($mock)->content()->forms();
    expect(fn () => $forms->find($uuid))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $forms->submit($uuid, new ContactFormSubmission('ticket')))->toThrow(InvalidArgumentException::class);
    $mock->assertSentCount(0);
})->with(['', '../forms', 'some-handle', '11111111-1111-4111-8111-111111111111/other', "11111111-1111-4111-8111-111111111111\n"]);

it('does not treat another successful status or acknowledgement as accepted', function (int $status, string $ackStatus) {
    $mock = new MockClient([MockResponse::make(['data' => ['status' => $ackStatus, 'message' => 'Response']], $status)]);
    expect(fn () => contactFormsClient($mock)->content()->forms()->submit(contactFormUuid(), new ContactFormSubmission('ticket')))
        ->toThrow(HydrationException::class);
    $mock->assertSentCount(1);
})->with([[200, 'accepted'], [201, 'accepted'], [202, 'sent']]);

it('maps contact failures through standard SDK errors and never retries a write', function (int $status, string $code, string $exceptionClass, bool $submit) {
    $errors = array_fill_keys(['payload', 'email', 'subject', 'message', 'contact_reason', 'website', 'submission_ticket'], ['Invalid value.']);
    $response = MockResponse::make(['code' => $code, 'message' => 'Private server detail', 'errors' => $errors], $status, ['Retry-After' => '42']);
    $mock = new MockClient([$response, $response, $response]);
    $forms = contactFormsClient($mock)->content()->forms();
    try {
        $submit ? $forms->submit(contactFormUuid(), new ContactFormSubmission('ticket')) : $forms->find(contactFormUuid());
    } catch (MembergyException $exception) {
        expect($exception)->toBeInstanceOf($exceptionClass)
            ->and($exception->getPrevious())->toBeNull()
            ->and($exception->getMessage())->not->toContain('Private server detail');
        if ($exception instanceof RequestValidationException) {
            expect($exception->errors)->toBe($errors)->and($exception->errorCode)->toBe('cms_validation_failed');
        }
        if ($exception instanceof RateLimitException) {
            expect($exception->retryAfterSeconds)->toBe(42);
        }
        if ($exception instanceof HttpRequestException) {
            expect($exception->status)->toBe($status);
        }
        $mock->assertSentCount(! $submit && $status === 503 ? 3 : 1);

        return;
    }
    throw new RuntimeException('Expected typed contact failure.');
})->with([
    [422, 'cms_validation_failed', RequestValidationException::class],
    [429, 'cms_rate_limited', RateLimitException::class],
    [404, 'cms_not_found', ResourceNotFoundException::class],
    [503, 'cms_contact_unavailable', HttpRequestException::class],
    [403, 'cms_forbidden', HttpRequestException::class],
    [401, 'cms_unauthenticated', AuthenticationException::class],
])->with(['GET' => false, 'POST' => true]);

it('does not replay a submission after losing the response at the transport boundary', function () {
    $attempts = 0;
    $failure = MockResponse::make()->throw(static function (PendingRequest $pending) use (&$attempts): FatalRequestException {
        $attempts++;

        return new FatalRequestException(
            new ConnectException('Private lost-response detail', $pending->createPsrRequest()), $pending,
        );
    });
    $mock = new MockClient([$failure, $failure, $failure]);
    try {
        contactFormsClient($mock)->content()->forms()->submit(contactFormUuid(), new ContactFormSubmission('ticket'));
    } catch (TransportException $exception) {
        expect($attempts)->toBe(1)->and($exception->getPrevious())->toBeNull()
            ->and($exception->getMessage())->not->toContain('Private lost-response detail');

        return;
    }
    throw new RuntimeException('Expected transport failure.');
});

it('keeps anonymous and user bound contact clients immutable', function () {
    $mock = new MockClient(array_fill(0, 4, MockResponse::make(contactFormPayload())));
    $public = contactFormsClient($mock);
    $member = $public->withUserToken('visitor-token');
    $anonymous = $member->withoutUserToken();
    foreach ([$member, $public, $anonymous, $member] as $client) {
        $client->content()->forms()->find(contactFormUuid());
    }
    $headers = array_map(static fn (Response $response): mixed => $response->getPendingRequest()->headers()->get('Authorization'), $mock->getRecordedResponses());
    expect($headers)->toBe(['Bearer visitor-token', null, null, 'Bearer visitor-token']);
});

it('suppresses contact request and connector diagnostic hooks containing tickets or payloads', function (bool $submit, bool $connectorHooks) {
    $request = $submit
        ? new SubmitContactFormRequest('demo', contactFormUuid(), new ContactFormSubmission('private-ticket', message: 'private-message'))
        : new ShowContactFormRequest('demo', contactFormUuid());
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', cmsToken: testCmsToken());
    $connector->withMockClient(new MockClient([MockResponse::make(['submission_ticket' => 'private-ticket'])]));
    $calls = 0;
    $owner = $connectorHooks ? $connector : $request;
    $owner->debugRequest(static function () use (&$calls): void {
        $calls++;
    })
        ->debugResponse(static function () use (&$calls): void {
            $calls++;
        });
    $connector->send($request);
    ob_start();
    var_dump($request);
    $dump = (string) ob_get_clean();
    expect($calls)->toBe(0)->and($dump)->not->toContain('private-ticket', 'private-message');
})->with([false, true])->with([false, true]);

it('redacts ticket and payload DTOs in native object dumps', function () {
    $form = ContactFormDefinition::fromArray(contactFormPayload('private-ticket')['data']);
    $submission = new ContactFormSubmission('private-ticket', message: 'private-message');
    ob_start();
    var_dump($form, $submission);
    $dump = (string) ob_get_clean();
    expect($dump)->not->toContain('private-ticket', 'private-message');
});

it('retains disabled field flags and rejects malformed native DTO payloads', function () {
    $payload = contactFormPayload();
    $payload['data']['definition']['fields'][3]['enabled'] = false;
    $payload['data']['definition']['fields'][3]['options'] = [];
    $form = ContactFormDefinition::fromArray($payload['data']);
    expect($form->definition->fields[3]->enabled)->toBeFalse()
        ->and($form->definition->fields[3]->options)->toBe([]);
    $payload['data']['definition']['fields'][0]['required'] = 'true';
    $mock = new MockClient([MockResponse::make($payload)]);
    expect(fn () => contactFormsClient($mock)->content()->forms()->find(contactFormUuid()))
        ->toThrow(HydrationException::class);
});

it('surfaces contact GET rate limits once with Retry-After and no cache access', function () {
    $store = new ForbiddenCache;
    $response = MockResponse::make(['code' => 'cms_rate_limited', 'message' => 'Too many requests.'], 429, ['Retry-After' => '42']);
    $mock = new MockClient([$response, $response, $response]);
    $connector = new MembergyConnector(
        'https://api.membergy.test', 'demo', responseCache: new ResponseCache($store, $store), retryInterval: 0,
        cmsToken: testCmsToken(), );
    $connector->withMockClient($mock);

    try {
        MembergyClient::fromConnector($connector)->content()->forms()->find(contactFormUuid());
    } catch (RateLimitException $exception) {
        expect($exception->retryAfterSeconds)->toBe(42)->and($exception->getPrevious())->toBeNull();
        $mock->assertSentCount(1);
        $mock->assertSent(fn (Request $request): bool => $request instanceof ShowContactFormRequest && $request->getMethod() === Method::GET);
        expect($store->reads)->toBe(0)->and($store->writes)->toBe(0);

        return;
    }

    throw new RuntimeException('Expected a contact rate limit exception.');
});

it('keeps contact GET transport retries bounded by the existing connector policy', function (bool $recover) {
    $attempts = 0;
    $failure = MockResponse::make()->throw(static function (PendingRequest $pending) use (&$attempts): FatalRequestException {
        $attempts++;

        return new FatalRequestException(new ConnectException('Synthetic contact connection failure.', $pending->createPsrRequest()), $pending);
    });
    $mock = new MockClient([$failure, $failure, $recover ? MockResponse::make(contactFormPayload()) : $failure]);
    try {
        $form = contactFormsClient($mock)->content()->forms()->find(contactFormUuid());
        expect($recover)->toBeTrue()->and($form->submissionTicket)->toBe('opaque-ticket');
    } catch (TransportException $exception) {
        expect($recover)->toBeFalse()->and($exception->getPrevious())->toBeNull();
    }
    // Saloon records responses only after a completed transport attempt.
    expect($attempts)->toBe($recover ? 2 : 3);
    $mock->assertSentCount($recover ? 1 : 0);
})->with(['recover on third attempt' => true, 'exhaust three attempts' => false]);

it('keeps contact GET server error retries before returning a fresh definition', function (int $status) {
    $failure = MockResponse::make(['code' => 'cms_contact_unavailable'], $status);
    $mock = new MockClient([$failure, $failure, MockResponse::make(contactFormPayload())]);
    expect(contactFormsClient($mock)->content()->forms()->find(contactFormUuid())->submissionTicket)->toBe('opaque-ticket');
    $mock->assertSentCount(3);
})->with([500, 503]);

it('keeps unrelated GET rate limit and server error retries unchanged', function (int $status) {
    $failure = MockResponse::make(['code' => 'cms_unavailable'], $status, ['Retry-After' => '42']);
    $mock = new MockClient([$failure, $failure, MockResponse::make(contractFixture('pages/show.json'))]);
    $page = contactFormsClient($mock)->content()->pages()->find('willkommen');
    expect($page->slug)->toBe('willkommen');
    $mock->assertSentCount(3);
})->with([429, 503]);
