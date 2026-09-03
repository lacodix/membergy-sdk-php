<?php

declare(strict_types=1);

use Lacodix\MembergySdk\Connectors\MembergyConnector;
use Lacodix\MembergySdk\DataObjects\Boilerplate;
use Lacodix\MembergySdk\DataObjects\Event;
use Lacodix\MembergySdk\DataObjects\NewsletterCategory;
use Lacodix\MembergySdk\DataObjects\NewsletterRequestResult;
use Lacodix\MembergySdk\Exceptions\ResourceNotFoundException;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\ListBoilerplatesRequest;
use Lacodix\MembergySdk\Requests\Content\ListEventsRequest;
use Lacodix\MembergySdk\Requests\Content\ListNewsletterCategoriesRequest;
use Lacodix\MembergySdk\Requests\Content\RequestNewsletterUnsubscribeLinkRequest;
use Lacodix\MembergySdk\Requests\Content\ShowBoilerplateRequest;
use Lacodix\MembergySdk\Requests\Content\ShowEventRequest;
use Lacodix\MembergySdk\Requests\Content\SubscribeNewsletterRequest;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;

function publicWebsiteClient(MockClient $mock, ?string $token = null): MembergyClient
{
    $connector = new MembergyConnector(
        baseUrl: 'https://api.membergy.test',
        tenant: 'demo',
        userToken: $token,
    );
    $connector->withMockClient($mock);

    return MembergyClient::fromConnector($connector);
}

it('hydrates the compatible event timeline with filters timezone and cursor pagination', function () {
    $mock = new MockClient([
        ListEventsRequest::class => MockResponse::make(contractFixture('events/index.json')),
        ShowEventRequest::class => MockResponse::make(contractFixture('events/show.json')),
    ]);
    $client = publicWebsiteClient($mock, 'member-token');

    $events = $client->content()->events()
        ->eventType('performance')
        ->tagIds(910001)
        ->participation(accepted: true)
        ->cursor('opaque-cursor')
        ->perPage(10)
        ->get();
    $event = $client->content()->events()->find('50000000-0000-4000-8000-000000000001');

    expect($events->items[0])->toBeInstanceOf(Event::class)
        ->and($events->nextCursor())->toBeNull()
        ->and($events->previousCursor())->toBeNull()
        ->and($events->hasMorePages())->toBeFalse()
        ->and($event->timezone)->toBe('Europe/Berlin')
        ->and($event->startAt?->format(DATE_ATOM))->toBe('2026-09-15T19:00:00+02:00')
        ->and($event->tags[0]->id)->toBe(910001);

    $mock->assertSent(function (Request $request): bool {
        return $request instanceof ListEventsRequest
            && $request->resolveEndpoint() === '/tenant/demo/content/events'
            && $request->query()->all() === [
                'event_type' => 'performance',
                'tags' => [910001],
                'pp' => [
                    'no_decision' => false,
                    'accepted' => true,
                    'rejected' => false,
                ],
                'cursor' => 'opaque-cursor',
                'per_page' => 10,
            ];
    });
    $mock->assertSent(function (ShowEventRequest $request): bool {
        return $request->resolveEndpoint() === '/tenant/demo/content/events/50000000-0000-4000-8000-000000000001';
    });
});

it('hydrates boilerplates only from the explicit public resource shape', function () {
    $mock = new MockClient([
        ListBoilerplatesRequest::class => MockResponse::make(contractFixture('boilerplates/index.json')),
        ShowBoilerplateRequest::class => MockResponse::make(contractFixture('boilerplates/show.json')),
    ]);
    $client = publicWebsiteClient($mock);

    $boilerplates = $client->content()->boilerplates()->page(1)->perPage(10)->get();
    $boilerplate = $client->content()->boilerplates()->find('rundschreiben-anrede');

    expect($boilerplates->items[0])->toBeInstanceOf(Boilerplate::class)
        ->and($boilerplates->total())->toBe(1)
        ->and($boilerplate->content)->toBe('<p>Liebe Musikerinnen und Musiker,</p>');

    $mock->assertSent(function (Request $request): bool {
        return $request instanceof ListBoilerplatesRequest
            && $request->resolveEndpoint() === '/tenant/demo/content/boilerplates'
            && $request->query()->all() === ['page' => 1, 'per_page' => 10];
    });
    $mock->assertSent(function (ShowBoilerplateRequest $request): bool {
        return $request->resolveEndpoint() === '/tenant/demo/content/boilerplates/rundschreiben-anrede';
    });
});

it('lists UUID newsletter categories and sends enumeration-safe mail requests', function () {
    $uuid = '70000000-0000-4000-8000-000000000001';
    $mock = new MockClient([
        ListNewsletterCategoriesRequest::class => MockResponse::make(contractFixture('newsletter/categories.json')),
        SubscribeNewsletterRequest::class => MockResponse::make(contractFixture('newsletter/confirmation-requested.json')),
        RequestNewsletterUnsubscribeLinkRequest::class => MockResponse::make(
            contractFixture('newsletter/unsubscribe-link-requested.json'),
        ),
    ]);
    $client = publicWebsiteClient($mock);

    $categories = $client->newsletter()->categories();
    $subscription = $client->newsletter()->subscribe('gast@example.test', [$uuid]);
    $unsubscription = $client->newsletter()->requestUnsubscribe('gast@example.test');

    expect($categories[0])->toBeInstanceOf(NewsletterCategory::class)
        ->and($categories[0]->uuid)->toBe($uuid)
        ->and($subscription)->toBeInstanceOf(NewsletterRequestResult::class)
        ->and($subscription->status)->toBe('confirmation_requested')
        ->and($unsubscription->status)->toBe('unsubscribe_link_requested');

    $mock->assertSent(function (Request $request): bool {
        return $request instanceof ListNewsletterCategoriesRequest
            && $request->resolveEndpoint() === '/tenant/demo/content/newsletter/categories';
    });
    $mock->assertSent(function (Request $request): bool {
        return $request instanceof SubscribeNewsletterRequest
            && $request->resolveEndpoint() === '/tenant/demo/content/newsletter/subscribe'
            && $request->body()->all() === [
                'email' => 'gast@example.test',
                'categories' => ['70000000-0000-4000-8000-000000000001'],
            ];
    });
    $mock->assertSent(function (Request $request): bool {
        return $request instanceof RequestNewsletterUnsubscribeLinkRequest
            && $request->resolveEndpoint() === '/tenant/demo/content/newsletter/unsubscribe'
            && $request->body()->all() === [
                'email' => 'gast@example.test',
            ];
    });
});

it('maps a newsletter category 404 to the stable SDK not-found exception', function () {
    $mock = new MockClient([
        ListNewsletterCategoriesRequest::class => MockResponse::make(
            contractFixture('errors/not-found.json'),
            404,
        ),
    ]);

    publicWebsiteClient($mock)->newsletter()->categories();
})->throws(ResourceNotFoundException::class, 'The requested Membergy resource was not found.');

it('rejects invalid event and newsletter filters before sending a request', function () {
    $client = publicWebsiteClient(new MockClient);

    expect(fn () => $client->content()->events()->eventType('unknown'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $client->content()->events()->tagIds(0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $client->newsletter()->subscribe('gast@example.test', []))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $client->newsletter()->subscribe('gast@example.test', ['not-a-uuid']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $client->newsletter()->subscribe('not-an-email', [
            '70000000-0000-4000-8000-000000000001',
        ]))
        ->toThrow(InvalidArgumentException::class);
});
