<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Membergy Base URL
    |---------------------------------------------------------------------------
    |
    | The root URL of your Membergy instance (no trailing /api). Different
    | values per environment are typical:
    |   production/default: https://membergy.app
    |   local example:      http://membergy.test
    |
    */
    'base_url' => env('MEMBERGY_URL', 'https://membergy.app'),

    /*
    |---------------------------------------------------------------------------
    | Tenant Slug
    |---------------------------------------------------------------------------
    |
    | The slug / route key of the tenant this app represents in Membergy.
    | Used to build the /api/v1/tenant/{tenant}/... path segment. One SDK
    | client is bound to exactly one tenant — multi-tenant setups should
    | build multiple clients rather than switching at runtime.
    |
    */
    'tenant' => env('MEMBERGY_TENANT'),

    /*
    |---------------------------------------------------------------------------
    | User Token (optional)
    |---------------------------------------------------------------------------
    |
    | A static Sanctum user token for server-to-server use. Most public CMS
    | calls do not need one. In a Laravel web application, a token stored in
    | the configured session key takes precedence for the current request.
    |
    */
    'user_token' => env('MEMBERGY_USER_TOKEN'),

    /*
    |---------------------------------------------------------------------------
    | User Token Session Key
    |---------------------------------------------------------------------------
    |
    | Laravel applications may put a Membergy user token into this session key.
    | The request-scoped SDK client then automatically exposes member content.
    |
    */
    'user_token_session_key' => env('MEMBERGY_USER_TOKEN_SESSION_KEY', 'membergy_user_token'),

    /*
    |---------------------------------------------------------------------------
    | API Version
    |---------------------------------------------------------------------------
    */
    'api_version' => env('MEMBERGY_API_VERSION', 'v1'),

    /*
    |---------------------------------------------------------------------------
    | Transport
    |---------------------------------------------------------------------------
    |
    | Only idempotent GET/HEAD requests retry. Rate limits and transient 5xx or
    | network failures use exponential backoff; writes are never replayed.
    |
    */
    'transport' => [
        'connect_timeout' => (float) env('MEMBERGY_CONNECT_TIMEOUT', 5),
        'request_timeout' => (float) env('MEMBERGY_REQUEST_TIMEOUT', 15),
        'tries' => (int) env('MEMBERGY_RETRY_TRIES', 3),
        'retry_interval' => (int) env('MEMBERGY_RETRY_INTERVAL_MS', 200),
    ],

    /*
    |---------------------------------------------------------------------------
    | Conditional response cache
    |---------------------------------------------------------------------------
    |
    | Public and member stores are separate policy boundaries even when they
    | point at the same Laravel cache driver. Member caching is opt-in and
    | still obeys private/no-store response directives from Membergy.
    |
    */
    'cache' => [
        'enabled' => (bool) env('MEMBERGY_CACHE_ENABLED', true),
        'store' => env('MEMBERGY_CACHE_STORE'),
        'member_enabled' => (bool) env('MEMBERGY_MEMBER_CACHE_ENABLED', false),
        'member_store' => env('MEMBERGY_MEMBER_CACHE_STORE'),
        'prefix' => env('MEMBERGY_CACHE_PREFIX', 'membergy-sdk'),
        'ttl' => (int) env('MEMBERGY_CACHE_TTL', 300),
        'stale_ttl' => (int) env('MEMBERGY_CACHE_STALE_TTL', 86400),
        'resource_ttls' => [
            'pages' => 300,
            'posts' => 120,
            'post-categories' => 300,
            'menus' => 300,
            'images' => 300,
            'files' => 300,
            'events' => 60,
            'boilerplates' => 300,
            'newsletter' => 300,
            'forms' => 0,
            'auth' => 0,
            'self-service' => 0,
        ],
    ],

];
