<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Tests\Integration;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Lacodix\MembergySdk\Contracts\ContentRenderer;
use Lacodix\MembergySdk\DataObjects\BlockDocument;
use Lacodix\MembergySdk\DataObjects\DynamicIncludes;
use Lacodix\MembergySdk\Exceptions\RegistrationIdentityException;
use Lacodix\MembergySdk\Laravel\MembergyServiceProvider;
use Lacodix\MembergySdk\Laravel\Rendering\BladeContentRenderer;
use Lacodix\MembergySdk\MembergyClient;
use Lacodix\MembergySdk\Requests\Content\ShowPageRequest;
use Lacodix\MembergySdk\Requests\Registrations\ShowRegistrationOfferRequest;
use Lacodix\MembergySdk\Requests\Registrations\SubmitRegistrationApplicationRequest;
use Lacodix\MembergySdk\Tests\Support\ArrayCache;
use Lacodix\MembergySdk\Tests\Support\RegistrationFixtures as Fixtures;
use Orchestra\Testbench\TestCase;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request as SaloonRequest;
use Saloon\Http\Response;
use SensitiveParameter;

final class LaravelIntegrationTest extends TestCase
{
    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [MembergyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('membergy.tenant', 'demo');
        $app['config']->set('membergy.cache.enabled', false);
    }

    public function test_it_uses_the_production_base_url_by_default_and_accepts_an_explicit_override(): void
    {
        self::assertSame('https://membergy.app', $this->app['config']->get('membergy.base_url'));
        self::assertSame(
            'https://membergy.app/api/v1',
            $this->app->make(MembergyClient::class)->connector()->resolveBaseUrl(),
        );

        $this->app['config']->set('membergy.base_url', 'http://local-membergy.test/');
        $this->app->forgetInstance(MembergyClient::class);

        self::assertSame(
            'http://local-membergy.test/api/v1',
            $this->app->make(MembergyClient::class)->connector()->resolveBaseUrl(),
        );
    }

    public function test_it_renders_recursive_block_documents_and_the_content_component(): void
    {
        $payload = json_decode(
            (string) file_get_contents(dirname(__DIR__).'/Fixtures/Contracts/Cms/v1/block-document.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($payload);
        $document = BlockDocument::fromArray($payload);
        $renderer = $this->app->make(ContentRenderer::class);

        self::assertInstanceOf(BladeContentRenderer::class, $renderer);
        $html = $renderer->render($document);

        self::assertStringContainsString('membergy-block--title', $html);
        self::assertStringContainsString('id="unser-verein"', $html);
        self::assertStringContainsString('Seit 1926 machen wir gemeinsam Musik.', $html);
        self::assertStringContainsString('membergy-column--one_third', $html);
        self::assertStringContainsString('<h2 class="membergy-block-title">Über uns</h2>', $html);
        self::assertStringContainsString(
            '/api/v1/tenant/demo/content/image/11111111-1111-4111-8111-111111111111',
            $html,
        );

        $component = Blade::render(
            '<x-membergy::content :document="$document" class="consumer-content" />',
            ['document' => $document],
        );
        self::assertStringContainsString('consumer-content', $component);
        self::assertStringContainsString('Unser Verein', $component);
    }

    public function test_it_skips_unknown_blocks_instead_of_rendering_raw_payloads(): void
    {
        $document = BlockDocument::fromArray([
            'schema_version' => 1,
            'blocks' => [[
                'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                'type' => 'future_widget',
                'version' => 1,
                'data' => ['raw_secret' => 'must-not-render'],
                'settings' => [],
            ]],
        ]);

        self::assertSame('', $this->app->make(ContentRenderer::class)->render($document));
    }

    public function test_it_renders_every_remaining_v1_block_template_and_dynamic_posts(): void
    {
        $document = BlockDocument::fromArray([
            'schema_version' => 1,
            'blocks' => [
                [
                    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa01',
                    'type' => 'title',
                    'version' => 1,
                    'data' => ['text' => 'Linked section', 'level' => 2],
                    'settings' => ['anchor' => 'linked-section'],
                ],
                [
                    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa02',
                    'type' => 'raw_html',
                    'version' => 1,
                    'data' => ['html' => '<p>Sanitized raw HTML</p>'],
                    'settings' => [],
                ],
                [
                    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa03',
                    'type' => 'downloads',
                    'version' => 1,
                    'data' => [
                        'title' => 'Files',
                        'items' => [[
                            'label' => 'Programme',
                            'file' => ['uuid' => '33333333-3333-4333-8333-333333333333'],
                        ]],
                    ],
                    'settings' => [],
                ],
                [
                    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa04',
                    'type' => 'agenda',
                    'version' => 1,
                    'data' => [
                        'title' => 'Schedule',
                        'intro' => null,
                        'items' => [[
                            'time' => '19:00',
                            'title' => 'Doors',
                            'text' => null,
                            'highlight' => true,
                            'link' => [
                                'url' => 'https://agenda.example.test',
                                'label' => 'Agenda details',
                                'open_in_new_tab' => true,
                                'rel' => ['nofollow', 'noopener'],
                            ],
                            'file' => null,
                        ]],
                    ],
                    'settings' => [],
                ],
                [
                    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa05',
                    'type' => 'logo_cloud',
                    'version' => 1,
                    'data' => [
                        'title' => 'Partners',
                        'items' => [[
                            'image' => [
                                'uuid' => '11111111-1111-4111-8111-111111111111',
                                'alt' => 'Partner',
                            ],
                            'link' => [
                                'url' => 'https://partner.example.test',
                                'label' => null,
                                'open_in_new_tab' => true,
                                'rel' => ['sponsored', 'noopener'],
                            ],
                        ]],
                    ],
                    'settings' => [],
                ],
                [
                    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa06',
                    'type' => 'video',
                    'version' => 1,
                    'data' => [
                        'video' => ['uuid' => '44444444-4444-4444-8444-444444444444'],
                        'poster' => null,
                        'caption' => 'Concert film',
                    ],
                    'settings' => [],
                ],
                [
                    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa07',
                    'type' => 'testimonials',
                    'version' => 1,
                    'data' => [
                        'title' => 'Voices',
                        'per_page' => 1,
                        'autoplay' => false,
                        'items' => [[
                            'text' => ['format' => 'html', 'value' => '<p>Excellent.</p>'],
                            'name' => 'Alex',
                        ]],
                    ],
                    'settings' => [],
                ],
                [
                    'id' => 'dddddddd-dddd-4ddd-8ddd-ddddddddddd1',
                    'type' => 'post_category',
                    'version' => 1,
                    'data' => [
                        'category_uuid' => '20000000-0000-4000-8000-000000000001',
                        'limit' => 3,
                        'order' => 'published_at_desc',
                        'include_teaser' => true,
                    ],
                    'settings' => [],
                ],
                [
                    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa09',
                    'type' => 'contact_form',
                    'version' => 1,
                    'data' => [
                        'form_handle' => 'contact',
                        'topics' => [['key' => 'general', 'label' => 'General']],
                    ],
                    'settings' => [],
                ],
                [
                    'id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa10',
                    'type' => 'anchor_navigation',
                    'version' => 1,
                    'data' => ['levels' => [2, 3]],
                    'settings' => [],
                ],
            ],
        ]);
        $includedPayload = json_decode(
            (string) file_get_contents(dirname(__DIR__).'/Fixtures/Contracts/Cms/v1/dynamic-includes.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($includedPayload);
        $html = $this->app->make(ContentRenderer::class)->render(
            $document,
            DynamicIncludes::fromArray($includedPayload),
        );

        foreach ([
            'Sanitized raw HTML',
            'Programme',
            'Doors',
            'Partners',
            'Concert film',
            'Excellent.',
            'Erfolg beim Wertungsspiel',
            'General',
            'href="#linked-section"',
        ] as $expected) {
            self::assertStringContainsString($expected, $html);
        }

        self::assertMatchesRegularExpression(
            '/<a href="https:\/\/agenda\.example\.test"\s+target="_blank"\s+rel="nofollow noopener"\s*>/',
            $html,
        );
        self::assertMatchesRegularExpression(
            '/<a href="https:\/\/partner\.example\.test"\s+target="_blank"\s+rel="sponsored noopener"\s*>/',
            $html,
        );
    }

    public function test_it_uses_the_request_session_token_in_a_scoped_client(): void
    {
        $request = Request::create('/');
        $session = new Store('membergy-test', new ArraySessionHandler(120));
        $session->start();
        $session->put('membergy_user_token', 'session-token');
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);
        $this->app->forgetInstance(MembergyClient::class);

        $client = $this->app->make(MembergyClient::class);

        self::assertSame(
            'member:'.hash('sha256', 'session-token'),
            $client->connector()->cacheScope(),
        );
    }

    public function test_static_laravel_tokens_cannot_supply_registration_identity_but_remain_usable_for_cms(): void
    {
        $client = $this->registrationClient();
        $mock = new MockClient([
            ShowRegistrationOfferRequest::class => Fixtures::response('offer-account'),
            SubmitRegistrationApplicationRequest::class => Fixtures::response('submitted-account'),
            ShowPageRequest::class => MockResponse::make(contractFixture('pages/show.json')),
        ]);
        $client->connector()->withMockClient($mock);

        foreach ([$client, $client->withCache(new ArrayCache, new ArrayCache)] as $candidate) {
            foreach (['offer', 'submit'] as $operation) {
                try {
                    if ($operation === 'offer') {
                        $candidate->registrationOffers()->find('membership');
                    } else {
                        $candidate->registrationApplications()->submit('membership', Fixtures::submission('submitted-account'));
                    }
                    self::fail('A static server token must not identify a registration applicant.');
                } catch (RegistrationIdentityException $error) {
                    self::assertSame('Invalid registration identity context for this request.', $error->getMessage());
                    self::assertNull($error->getPrevious());
                    self::assertStringNotContainsString('synthetic-static-server-token', (string) $error);
                }
            }
        }
        $mock->assertSentCount(0);

        $client->content()->pages()->find('willkommen');
        $mock->assertSent(fn (ShowPageRequest $request, Response $response): bool => $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer synthetic-static-server-token');
        $mock->assertSentCount(1);
    }

    public function test_the_applicant_session_token_overrides_static_config_for_offer_and_submission(): void
    {
        $client = $this->registrationClient('synthetic-session-applicant-token');
        $mock = new MockClient([Fixtures::response('offer-account'), Fixtures::response('submitted-account')]);
        $client->connector()->withMockClient($mock);
        $account = $client->withCache(new ArrayCache, new ArrayCache);

        self::assertSame('account.qa@example.test', $account->registrationOffers()->find('membership')->applicant?->email);
        self::assertSame('verified', $account->registrationApplications()->submit('membership', Fixtures::submission('submitted-account'))->status);
        foreach ([ShowRegistrationOfferRequest::class, SubmitRegistrationApplicationRequest::class] as $class) {
            $mock->assertSent(fn (SaloonRequest $request, Response $response): bool => $request instanceof $class
                && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer synthetic-session-applicant-token');
        }
        $mock->assertSentCount(2);
    }

    public function test_explicit_account_and_guest_copies_preserve_identity_without_mutating_the_static_client(): void
    {
        $static = $this->registrationClient();
        $mock = new MockClient([Fixtures::response('offer-account'), Fixtures::response('submitted-account'), Fixtures::response('offer-guest')]);
        $static->connector()->withMockClient($mock);
        $account = $static->withUserToken('synthetic-explicit-applicant-token')->withCache(new ArrayCache, new ArrayCache);
        $guest = $static->withoutUserToken()->withCache(new ArrayCache, new ArrayCache);

        self::assertSame('account.qa@example.test', $account->registrationOffers()->find('membership')->applicant?->email);
        self::assertSame('verified', $account->registrationApplications()->submit('membership', Fixtures::submission('submitted-account'))->status);
        self::assertNull($guest->registrationOffers()->find('membership')->applicant);
        foreach ([ShowRegistrationOfferRequest::class, SubmitRegistrationApplicationRequest::class] as $class) {
            $mock->assertSent(fn (SaloonRequest $request, Response $response): bool => $request instanceof $class
                && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer synthetic-explicit-applicant-token');
        }
        $mock->assertSent(fn (ShowRegistrationOfferRequest $request, Response $response): bool => $response->getPendingRequest()->headers()->get('Authorization') === null);
        try {
            $static->registrationOffers()->find('membership');
            self::fail('Copies must leave the original static-token client protected.');
        } catch (RegistrationIdentityException) {
            $mock->assertSentCount(3);
        }

        $explicit = new MembergyClient('https://sdk.membergy.test', 'contract-club', userToken: 'synthetic-constructor-applicant-token');
        $explicitMock = new MockClient([Fixtures::response('offer-account')]);
        $explicit->connector()->withMockClient($explicitMock);
        self::assertSame('account.qa@example.test', $explicit->registrationOffers()->find('membership')->applicant?->email);
        $explicitMock->assertSent(fn (ShowRegistrationOfferRequest $request, Response $response): bool => $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer synthetic-constructor-applicant-token');
        $explicitMock->assertSentCount(1);
    }

    private function registrationClient(#[SensitiveParameter] ?string $sessionToken = null): MembergyClient
    {
        $this->app['config']->set('membergy.base_url', 'https://sdk.membergy.test');
        $this->app['config']->set('membergy.tenant', 'contract-club');
        $this->app['config']->set('membergy.user_token', 'synthetic-static-server-token');
        $request = Request::create('/registration');
        if ($sessionToken !== null) {
            $session = new Store('membergy-registration-test', new ArraySessionHandler(120));
            $session->start();
            $session->put('membergy_user_token', $sessionToken);
            $request->setLaravelSession($session);
        }
        $this->app->instance('request', $request);
        $this->app->forgetInstance(MembergyClient::class);

        return $this->app->make(MembergyClient::class);
    }

    public function test_it_registers_the_install_command_and_publishable_assets(): void
    {
        self::assertArrayHasKey('membergy:install', Artisan::all());
        self::assertArrayHasKey('membergy:cache-clear', Artisan::all());
        self::assertNotEmpty(ServiceProvider::pathsToPublish(
            MembergyServiceProvider::class,
            'membergy-config',
        ));
        self::assertNotEmpty(ServiceProvider::pathsToPublish(
            MembergyServiceProvider::class,
            'membergy-views',
        ));
    }

    public function test_it_invalidates_a_resource_cache_generation_through_artisan(): void
    {
        $this->app['config']->set('membergy.cache.enabled', true);
        $this->app->forgetInstance(MembergyClient::class);

        self::assertSame(0, Artisan::call('membergy:cache-clear', [
            'resource' => 'posts',
        ]));

        $store = $this->app->make(CacheFactory::class)->store();
        $generation = $store->get(hash('sha256', 'membergy-sdk|generation|resource:posts'));

        self::assertIsString($generation);
        self::assertNotSame('1', $generation);
        self::assertStringContainsString("Membergy 'posts' cache invalidated.", Artisan::output());
    }
}
