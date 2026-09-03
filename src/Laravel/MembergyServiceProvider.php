<?php

declare(strict_types=1);

namespace Lacodix\MembergySdk\Laravel;

use Illuminate\Support\ServiceProvider;
use Lacodix\MembergySdk\MembergyClient;

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
            __DIR__ . '/../../config/membergy.php',
            'membergy'
        );

        $this->app->singleton(MembergyClient::class, static function ($app): MembergyClient {
            $config = $app['config']['membergy'];

            return new MembergyClient(
                baseUrl: (string) $config['base_url'],
                tenant: (string) $config['tenant'],
                userToken: $config['app_token'] ?? null,
                apiVersion: (string) ($config['api_version'] ?? 'v1'),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/membergy.php' => $this->app->configPath('membergy.php'),
            ], 'membergy-config');
        }
    }
}
