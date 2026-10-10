<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\BankDetails;
use Lacodix\MembergySdk\DataObjects\ContactDetail;
use Lacodix\MembergySdk\DataObjects\FormDefinition;
use Lacodix\MembergySdk\DataObjects\FormField;
use Lacodix\MembergySdk\DataObjects\FormOption;
use Lacodix\MembergySdk\DataObjects\FormRow;
use Lacodix\MembergySdk\DataObjects\MemberNewsletterSubscription;
use Lacodix\MembergySdk\DataObjects\PasswordUpdateResult;
use Lacodix\MembergySdk\DataObjects\PersonProfile;
use Lacodix\MembergySdk\DataObjects\TenantAccess;
use Lacodix\MembergySdk\Exceptions\AuthenticationException;
use Lacodix\MembergySdk\Exceptions\RateLimitException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Exceptions\SelfServiceForbiddenException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Me\AbstractSelfServiceRequest;
use Lacodix\MembergySdk\Requests\Me\ShowNewsletterSubscriptionsRequest;
use Lacodix\MembergySdk\Requests\Me\ShowPersonFormRequest;
use Lacodix\MembergySdk\Requests\Me\ShowPersonRequest;
use Lacodix\MembergySdk\Requests\Me\ShowTenantAccessRequest;
use Lacodix\MembergySdk\Requests\Me\UpdateNewsletterSubscriptionsRequest;
use Lacodix\MembergySdk\Requests\Me\UpdatePasswordRequest;
use Lacodix\MembergySdk\Requests\Me\UpdatePersonRequest;
use Lacodix\MembergySdk\Resources\PasswordResource;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;

function selfServiceClient(MockClient $mock, ?string $token = 'member-token'): MembergyClient
{
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'demo',
        userToken: $token,
    );
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

it('proves tenant access independently of person self-service', function () {
    $mock = new MockClient([
        ShowTenantAccessRequest::class => MockResponse::make(contractFixture('me/access.json')),
    ]);

    $access = selfServiceClient($mock)->me()->access()->get();

    expect($access)->toBeInstanceOf(TenantAccess::class)
        ->and($access->tenantSlug)->toBe('dstg-sachsen')
        ->and($access->displayName)->toBe('Alex Member')
        ->and($access->extra)->toBe([]);

    $mock->assertSent(function (Request $request, Response $response): bool {
        $pending = $response->getPendingRequest();

        return $request instanceof ShowTenantAccessRequest
            && $request->resolveEndpoint() === '/tenant/demo/me/access'
            && $pending->headers()->get('Accept') === AbstractSelfServiceRequest::MEDIA_TYPE
            && $pending->headers()->get('Authorization') === 'Bearer member-token';
    });
});

it('hydrates the authenticated person profile and dynamic form through self-service-v1', function () {
    $mock = new MockClient([
        ShowPersonRequest::class => MockResponse::make(contractFixture('me/person.json')),
        ShowPersonFormRequest::class => MockResponse::make(contractFixture('me/person-form.json')),
    ]);

    $person = selfServiceClient($mock)->me()->person()->get();
    $form = selfServiceClient($mock)->me()->person()->form();

    expect($person)->toBeInstanceOf(PersonProfile::class)
        ->and($person->firstname)->toBe('Alex')
        ->and($person->lastname)->toBe('Member')
        ->and($person->email)->toBe('member@example.test')
        ->and($person->dateOfBirth)->toBe('1985-06-15')
        ->and($person->updatedAt?->format(DATE_ATOM))->toBe('2026-08-11T10:00:00+00:00')
        ->and($person->extra)->toBe([])
        ->and($form)->toBeInstanceOf(FormDefinition::class)
        ->and($form->name)->toBe('person')
        ->and($form->fields[0])->toBeInstanceOf(FormField::class)
        ->and($form->fields[0]->name)->toBe('firstname')
        ->and($form->fields[0]->required)->toBeTrue()
        ->and($form->fields[6]->name)->toBe('salutation')
        ->and($form->fields[6]->options[0])->toBeInstanceOf(FormOption::class)
        ->and($form->fields[6]->options[0]->value)->toBe('mr')
        ->and($form->fields[6]->options[0]->label)->toBe('Herr')
        ->and($form->values['metadata'])->toBe(['shirt_size' => 'M']);

    $shirtSize = array_values(array_filter(
        $form->fields,
        fn ($field): bool => $field->name === 'metadata.shirt_size',
    ));

    expect($shirtSize)->toHaveCount(1)
        ->and($shirtSize[0]->label)->toBe('Shirt size');

    $mock->assertSent(function (Request $request, Response $response): bool {
        $pending = $response->getPendingRequest();

        return $request instanceof ShowPersonRequest
            && $request->resolveEndpoint() === '/tenant/demo/me/person'
            && $pending->headers()->get('Accept') === AbstractSelfServiceRequest::MEDIA_TYPE
            && $pending->headers()->get('Authorization') === 'Bearer member-token';
    });
    $mock->assertSent(fn (Request $request): bool => $request instanceof ShowPersonFormRequest
        && $request->resolveEndpoint() === '/tenant/demo/me/person/form');
});

it('updates only caller-provided person values and hydrates the returned profile', function () {
    $mock = new MockClient([
        UpdatePersonRequest::class => MockResponse::make(contractFixture('me/person-updated.json')),
    ]);
    $values = [
        'firstname' => 'Jamie',
        'lastname' => 'Updated',
        'metadata' => ['shirt_size' => 'L'],
    ];

    $person = selfServiceClient($mock)->me()->person()->update($values);

    expect($person)->toBeInstanceOf(PersonProfile::class)
        ->and($person->firstname)->toBe('Jamie')
        ->and($person->lastname)->toBe('Updated')
        ->and($person->gender)->toBe('female');

    $mock->assertSent(function (UpdatePersonRequest $request, Response $response) use ($values): bool {
        $pending = $response->getPendingRequest();

        return $request->resolveEndpoint() === '/tenant/demo/me/person'
            && $request->body()->all() === $values
            && $pending->headers()->get('Accept') === AbstractSelfServiceRequest::MEDIA_TYPE
            && $pending->headers()->get('Authorization') === 'Bearer member-token';
    });
});

it('reads and atomically replaces the authenticated member newsletter categories', function () {
    $mock = new MockClient([
        ShowNewsletterSubscriptionsRequest::class => MockResponse::make(
            contractFixture('me/newsletter.json'),
        ),
        UpdateNewsletterSubscriptionsRequest::class => MockResponse::make(
            contractFixture('me/newsletter-updated.json'),
        ),
    ]);
    $categories = [
        '11111111-1111-4111-8111-111111111111',
        '22222222-2222-4222-8222-222222222222',
    ];
    $newsletter = selfServiceClient($mock)->me()->newsletter();

    $current = $newsletter->get();
    $updated = $newsletter->replace($categories);

    expect($current)->toBeInstanceOf(MemberNewsletterSubscription::class)
        ->and($current->email)->toBe('member@example.test')
        ->and($current->categories)->toBe([$categories[0]])
        ->and($updated->categories)->toBe($categories)
        ->and($updated->extra)->toBe([]);

    $mock->assertSent(fn (Request $request): bool => $request instanceof ShowNewsletterSubscriptionsRequest
        && $request->resolveEndpoint() === '/tenant/demo/me/newsletter');
    $mock->assertSent(function (
        UpdateNewsletterSubscriptionsRequest $request,
        Response $response,
    ) use ($categories): bool {
        $pending = $response->getPendingRequest();

        return $request->resolveEndpoint() === '/tenant/demo/me/newsletter'
            && $request->body()->all() === ['categories' => $categories]
            && $pending->headers()->get('Accept') === AbstractSelfServiceRequest::MEDIA_TYPE
            && $pending->headers()->get('Authorization') === 'Bearer member-token';
    });
});

it('allows replacing member newsletter categories with an empty list', function () {
    $mock = new MockClient([
        UpdateNewsletterSubscriptionsRequest::class => MockResponse::make([
            'data' => [
                'email' => 'member@example.test',
                'categories' => [],
            ],
        ]),
    ]);

    $result = selfServiceClient($mock)->me()->newsletter()->replace([]);

    expect($result->categories)->toBe([]);
    $mock->assertSent(fn (UpdateNewsletterSubscriptionsRequest $request): bool => $request->body()->all() === [
        'categories' => [],
    ]);
});

it('rejects malformed or duplicate newsletter category UUIDs before sending a request', function (
    array $categories,
) {
    selfServiceClient(new MockClient)->me()->newsletter()->replace($categories);
})->with([
    'malformed UUID' => [['not-a-uuid']],
    'duplicate UUID' => [[
        '11111111-1111-4111-8111-111111111111',
        '11111111-1111-4111-8111-111111111111',
    ]],
])->throws(InvalidArgumentException::class);

it('maps self-service validation errors to the typed SDK exception', function () {
    $mock = new MockClient([
        UpdateNewsletterSubscriptionsRequest::class => MockResponse::make(
            contractFixture('me/errors/validation.json'),
            422,
        ),
    ]);

    try {
        selfServiceClient($mock)->me()->newsletter()->replace([
            '33333333-3333-4333-8333-333333333333',
        ]);
    } catch (RequestValidationException $exception) {
        expect($exception->errors)->toBe([
            'categories' => ['One or more categories are unavailable.'],
        ]);

        return;
    }

    throw new RuntimeException('Expected a typed self-service validation exception.');
});

it('maps self-service authentication, authorization, and lookup failures', function (
    string $fixture,
    int $status,
    string $exception,
) {
    $mock = new MockClient([
        ShowPersonRequest::class => MockResponse::make(contractFixture($fixture), $status),
    ]);

    expect(fn () => selfServiceClient($mock)->me()->person()->get())
        ->toThrow($exception);
})->with([
    'unauthenticated' => [
        'me/errors/unauthenticated.json',
        401,
        AuthenticationException::class,
    ],
    'forbidden' => [
        'me/errors/forbidden.json',
        403,
        SelfServiceForbiddenException::class,
    ],
    'not found' => [
        'me/errors/not-found.json',
        404,
        ResourceNotFoundException::class,
    ],
]);

it('maps tenant access authentication, authorization, and lookup failures', function (
    string $fixture,
    int $status,
    string $exception,
) {
    $mock = new MockClient([
        ShowTenantAccessRequest::class => MockResponse::make(contractFixture($fixture), $status),
    ]);

    expect(fn () => selfServiceClient($mock)->me()->access()->get())
        ->toThrow($exception);
})->with([
    'unauthenticated' => [
        'me/errors/unauthenticated.json',
        401,
        AuthenticationException::class,
    ],
    'forbidden' => [
        'me/errors/forbidden.json',
        403,
        SelfServiceForbiddenException::class,
    ],
    'not found' => [
        'me/errors/not-found.json',
        404,
        ResourceNotFoundException::class,
    ],
]);

it('maps a rate-limited tenant access probe including retry-after', function () {
    $mock = new MockClient([
        ShowTenantAccessRequest::class => MockResponse::make(
            contractFixture('me/errors/rate-limited.json'),
            429,
            ['Retry-After' => '37'],
        ),
    ]);

    try {
        selfServiceClient($mock)->me()->access()->get();
    } catch (RateLimitException $exception) {
        expect($exception->retryAfterSeconds)->toBe(37);

        return;
    }

    throw new RuntimeException('Expected a typed tenant access rate limit exception.');
});

it('hydrates repeater child fields typed rows and owner bank data without losing unknown values', function () {
    $mock = new MockClient([
        ShowPersonRequest::class => MockResponse::make(contractFixture('me/person.json')),
        ShowPersonFormRequest::class => MockResponse::make(contractFixture('me/person-form.json')),
    ]);
    $resource = selfServiceClient($mock)->me()->person();
    $person = $resource->get();
    $form = $resource->form();
    $repeater = array_values(array_filter($form->fields, fn ($field): bool => $field->type === 'repeater'))[0];

    expect($repeater->name)->toBe('contact_details')->and($repeater->max)->toBe(20)
        ->and(array_map(fn ($field): string => $field->name, $repeater->fields))->toBe(['type', 'value', 'sharing_scope'])
        ->and($form->repeaterRows('contact_details')[0]->key)->toBe('78001')
        ->and($person->contactDetails[0]->key)->toBe('78001')
        ->and($person->contactDetails[0]->value)->toBe('contact@example.test')
        ->and($person->bankDetails)->toBeInstanceOf(BankDetails::class);

    $data = contractFixture('me/person.json')['data'];
    $data['bank_details']['iban'] = 'DE89370400440532013000';
    $data['bank_details']['future'] = 'preserved';
    $data['contact_details'][78001]['future'] = 'preserved';
    $profile = PersonProfile::fromArray($data);
    expect($profile->bankDetails->iban)->toBe('DE89370400440532013000')
        ->and($profile->bankDetails->extra)->toBe(['future' => 'preserved'])
        ->and($profile->contactDetails[0]->extra)->toBe(['future' => 'preserved']);
});

it('submits typed contact rows with stable existing and client UUID keys', function () {
    $mock = new MockClient([UpdatePersonRequest::class => MockResponse::make(contractFixture('me/person-updated.json'))]);
    $rows = [
        new ContactDetail('78001', 'private_email', 'new@example.test', 'club'),
        new FormRow('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', [
            'type' => 'private_phone', 'value' => '+493512345678', 'sharing_scope' => 'private',
        ]),
    ];
    selfServiceClient($mock)->me()->person()->update(['contact_details' => $rows]);
    $mock->assertSent(function (UpdatePersonRequest $request): bool {
        $values = (array) $request->body()->get('contact_details');

        return array_keys($values) === [78001, 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa']
            && $values[78001] === ['type' => 'private_email', 'value' => 'new@example.test', 'sharing_scope' => 'club'];
    });
});

it('hydrates a management destination and preserves null for a plain member', function () {
    $mock = new MockClient([
        ShowTenantAccessRequest::class => MockResponse::make(contractFixture('me/access-management.json')),
    ]);
    $access = selfServiceClient($mock)->me()->access()->get();
    expect($access->managementUrl)->toBe('https://membergy.example.test/dashboard/management/80008')
        ->and($access->extra)->toBe([]);
    expect(TenantAccess::fromArray(['tenant_slug' => 'demo', 'display_name' => 'Member'])->managementUrl)->toBeNull();
});

it('updates an authenticated password with a typed result and a strict request body', function () {
    $mock = new MockClient([
        UpdatePasswordRequest::class => MockResponse::make(contractFixture('me/password-updated.json')),
    ]);
    $result = selfServiceClient($mock)->me()->password()->update('OldPassword123!', 'NewPassword456!', 'NewPassword456!');
    expect($result)->toBeInstanceOf(PasswordUpdateResult::class)
        ->and($result->status)->toBe('password_updated')->and($result->extra)->toBe([]);
    $mock->assertSent(function (Request $request): bool {
        return $request instanceof UpdatePasswordRequest
            && $request->getMethod()->value === 'PUT'
            && $request->resolveEndpoint() === '/tenant/demo/me/password'
            && $request->body()->all() === [
                'current_password' => 'OldPassword123!', 'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ];
    });
});

it('maps password validation and throttling to existing typed exceptions', function (int $status, array $payload, string $exception) {
    $mock = new MockClient([
        UpdatePasswordRequest::class => MockResponse::make($payload, $status, ['Retry-After' => '60']),
    ]);
    expect(fn () => selfServiceClient($mock)->me()->password()->update('wrong', 'NewPassword456!', 'NewPassword456!'))
        ->toThrow($exception);
})->with([
    'validation' => [422, ['message' => 'The self-service request values are invalid.', 'code' => 'self_service_validation_failed', 'errors' => ['current_password' => ['Invalid current password.']]], RequestValidationException::class],
    'rate limit' => [429, ['message' => 'Too many requests.', 'code' => 'self_service_rate_limited'], RateLimitException::class],
]);

it('suppresses request and connector password debugging and marks secret parameters sensitive', function () {
    $mock = new MockClient([
        UpdatePasswordRequest::class => MockResponse::make(['data' => ['status' => 'password_updated']]),
    ]);
    $connector = new MembergyConnector('https://api.membergy.test', 'demo', 'member-token');
    $connector->withMockClient($mock);
    $called = false;
    $connector->debugRequest(function () use (&$called): void {
        $called = true;
    });
    $request = new UpdatePasswordRequest('demo', 'OldPassword123!', 'NewPassword456!', 'NewPassword456!');
    $request->debugRequest(function () use (&$called): void {
        $called = true;
    });
    $connector->send($request);
    expect($called)->toBeFalse()
        ->and(json_encode($request->__debugInfo()))->not->toContain('OldPassword123!', 'NewPassword456!');
    foreach ([PasswordResource::class => 'update', UpdatePasswordRequest::class => '__construct'] as $class => $method) {
        foreach ((new ReflectionMethod($class, $method))->getParameters() as $parameter) {
            if ($parameter->getName() !== 'tenant') {
                expect($parameter->getAttributes(SensitiveParameter::class))->toHaveCount(1);
            }
        }
    }
});
