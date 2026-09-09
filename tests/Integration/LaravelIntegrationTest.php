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
use Lacodix\MembergySdk\Laravel\MembergyServiceProvider;
use Lacodix\MembergySdk\Laravel\Rendering\BladeContentRenderer;
use Lacodix\MembergySdk\MembergyClient;
use Orchestra\Testbench\TestCase;

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
        $generation = $store->get('membergy-sdk:generation:resource:posts');

        self::assertIsString($generation);
        self::assertNotSame('1', $generation);
        self::assertStringContainsString("Membergy 'posts' cache invalidated.", Artisan::output());
    }
}
