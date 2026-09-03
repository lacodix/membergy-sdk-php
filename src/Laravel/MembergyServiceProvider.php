<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Lacodix\MembergySdk\Cache\CacheOptions;
use Lacodix\MembergySdk\Cache\ResponseCache;
use Lacodix\MembergySdk\Cache\SystemCacheClock;
use Lacodix\MembergySdk\Contracts\ContentRenderer;
use Lacodix\MembergySdk\Laravel\Commands\MembergyCacheClearCommand;
use Lacodix\MembergySdk\Laravel\Commands\MembergyInstallCommand;
use Lacodix\MembergySdk\Laravel\Rendering\BladeContentRenderer;
use Lacodix\MembergySdk\Media\MediaUrlFactory;
use Lacodix\MembergySdk\MembergyClient;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

/**
 * Laravel integration for the Membergy SDK.
 *
 * This class is only loaded when the host application is a Laravel
 * application — Composer autoloads the class file, but Laravel's
 * package:discover only picks up this provider when the framework
 * is actually running.
 *
 * In non-Laravel hosts (WordPress, CLI, plain PHP) this class is
 * inert: the base ServiceProvider class comes from illuminate/support,
 * which is only installed alongside Laravel. Calling the Laravel
 * integration without Laravel present will fail loudly at install
 * time rather than silently at runtime.
 */
class MembergyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/membergy.php',
            'membergy'
        );

        $this->app->singleton(ResponseCache::class, static function ($app): ResponseCache {
            $config = $app['config']['membergy'];
            $cacheConfig = $config['cache'] ?? [];
            $cacheFactory = $app->make(CacheFactory::class);
            $publicStore = $cacheFactory->store($cacheConfig['store'] ?? null);
            if (! $publicStore instanceof CacheInterface) {
                throw new RuntimeException('The configured Membergy cache store must implement PSR-16.');
            }

            $memberStore = null;
            if ((bool) ($cacheConfig['member_enabled'] ?? false)) {
                $memberStore = $cacheFactory->store(
                    $cacheConfig['member_store'] ?? $cacheConfig['store'] ?? null,
                );
                if (! $memberStore instanceof CacheInterface) {
                    throw new RuntimeException('The configured Membergy member cache store must implement PSR-16.');
                }
            }

            return new ResponseCache(
                publicStore: $publicStore,
                memberStore: $memberStore,
                options: new CacheOptions(
                    prefix: (string) ($cacheConfig['prefix'] ?? 'membergy-sdk'),
                    defaultTtl: (int) ($cacheConfig['ttl'] ?? 300),
                    staleTtl: (int) ($cacheConfig['stale_ttl'] ?? 86400),
                    resourceTtls: (array) ($cacheConfig['resource_ttls'] ?? []),
                ),
                clock: new SystemCacheClock,
            );
        });

        $this->app->scoped(MembergyClient::class, static function ($app): MembergyClient {
            $config = $app['config']['membergy'];
            $transport = $config['transport'] ?? [];
            $token = $config['user_token'] ?? null;
            $request = $app->bound('request') ? $app->make('request') : null;
            if ($request instanceof Request && $request->hasSession()) {
                $sessionToken = $request->session()->get(
                    (string) ($config['user_token_session_key'] ?? 'membergy_user_token'),
                );
                if (is_string($sessionToken) && $sessionToken !== '') {
                    $token = $sessionToken;
                }
            }

            return new MembergyClient(
                baseUrl: (string) $config['base_url'],
                tenant: (string) $config['tenant'],
                userToken: is_string($token) && $token !== '' ? $token : null,
                apiVersion: (string) ($config['api_version'] ?? 'v1'),
                responseCache: (bool) ($config['cache']['enabled'] ?? true)
                    ? $app->make(ResponseCache::class)
                    : null,
                connectTimeout: (float) ($transport['connect_timeout'] ?? 5),
                requestTimeout: (float) ($transport['request_timeout'] ?? 15),
                tries: (int) ($transport['tries'] ?? 3),
                retryInterval: (int) ($transport['retry_interval'] ?? 200),
            );
        });

        $this->app->scoped(MediaUrlFactory::class, static function ($app): MediaUrlFactory {
            $client = $app->make(MembergyClient::class);

            return $client->mediaUrls();
        });

        $this->app->singleton(BladeContentRenderer::class);
        $this->app->alias(BladeContentRenderer::class, ContentRenderer::class);
    }

    public function boot(): void
    {
        $views = __DIR__.'/../../resources/views';
        $this->loadViewsFrom($views, 'membergy');
        Blade::componentNamespace(
            'Lacodix\\MembergySdk\\Laravel\\View\\Components',
            'membergy',
        );
        Blade::anonymousComponentPath($views.'/components', 'membergy');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/membergy.php' => $this->app->configPath('membergy.php'),
            ], 'membergy-config');
            $this->publishes([
                $views => $this->app->resourcePath('views/vendor/membergy'),
            ], 'membergy-views');
            $this->commands([
                MembergyCacheClearCommand::class,
                MembergyInstallCommand::class,
            ]);
        }
    }
}
