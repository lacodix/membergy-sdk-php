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
    |   production:   https://members.example.org
    |   staging:      https://staging.members.example.org
    |
    */
    'base_url' => env('MEMBERGY_URL', 'https://members.example.org'),

    /*
    |---------------------------------------------------------------------------
    | Tenant Slug
    |---------------------------------------------------------------------------
    |
    | The slug / route key of the tenant this app represents in Membergy.
    | Used to build the /api/tenant/{tenant}/... path segment. One SDK
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

];
