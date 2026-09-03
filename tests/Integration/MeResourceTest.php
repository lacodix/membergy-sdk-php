<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\FormDefinition;
use Lacodix\MembergySdk\DataObjects\MemberNewsletterSubscription;
use Lacodix\MembergySdk\DataObjects\PersonProfile;
use Lacodix\MembergySdk\Exceptions\AuthenticationException;
use Lacodix\MembergySdk\Exceptions\RequestValidationException;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\Exceptions\SelfServiceForbiddenException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Me\AbstractSelfServiceRequest;
use Lacodix\MembergySdk\Requests\Me\ShowNewsletterSubscriptionsRequest;
use Lacodix\MembergySdk\Requests\Me\ShowPersonFormRequest;
use Lacodix\MembergySdk\Requests\Me\ShowPersonRequest;
use Lacodix\MembergySdk\Requests\Me\UpdateNewsletterSubscriptionsRequest;
use Lacodix\MembergySdk\Requests\Me\UpdatePersonRequest;
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
