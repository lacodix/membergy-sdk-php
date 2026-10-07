# Membergy SDK for PHP

PHP SDK for the [Membergy](https://membergy.app) API.

**Framework-agnostic**: works in any PHP 8.3+ project (Laravel, WordPress,
Symfony, plain PHP). Ships with an optional Laravel integration
(ServiceProvider, Facade, config publishing) that auto-activates when
Laravel is present.

## Status

Early development. The versioned CMS v1 slice and the binding S-1 through S-5
website-ready scope are implemented:

- [x] `MembergyClient` + `MembergyConnector` (Saloon-based)
- [x] `content()->pages()` (list, find, dynamic includes)
- [x] `content()->posts()` (list, category filter, find, dynamic includes)
- [x] `content()->postCategories()`
- [x] `content()->menus()` with recursive typed targets
- [x] `content()->images()` / `->files()`
- [x] `content()->events()` / `->boilerplates()`
- [x] `content()->search()` (typed page, post and event groups with continuation)
- [x] `newsletter()` (UUID categories, DOI subscribe / headless confirmation / signed unsubscribe request)
- [x] Typed BlockDocument v1 DTOs, `UnknownBlock`, `UnknownMenuTarget`
- [x] Configurable `MenuUrlResolver` foundation
- [x] `auth()` (credentials token, current-token revocation, password reset)
- [x] `me()->access()` (tenant access proof independent of person self-service)
- [x] `me()->person()` (profile, dynamic form, updates)
- [x] `me()->newsletter()` (member-owned category subscriptions)
- [x] Conditional public/member cache partitions, ETag revalidation and explicit purge
- [x] Optional recursive Blade renderer, responsive image helpers and Laravel installer
- [x] API/SDK compatibility matrix, bounded retries, timeouts and rate-limit exception

## Requirements

- PHP 8.3+
- A Membergy instance with the public content API enabled for your tenant.

## Installation

```bash
composer require lacodix/membergy-sdk-php
```

## Usage (framework-agnostic)

```php
use Lacodix\MembergySdk\Enums\EventVisibilityType;
use Lacodix\MembergySdk\Enums\SearchResultType;
use Lacodix\MembergySdk\MembergyClient;

$client = new MembergyClient(
    baseUrl: 'https://membergy.app',
    tenant:  'my-club',
);

$page = $client->content()->pages()->find('willkommen');

foreach ($page->content->blocks as $block) {
    // Match concrete DTOs such as TitleBlock, TextBlock or ColumnsBlock.
}

$posts = $client->content()->posts()
    ->category('news')
    ->perPage(10)
    ->get();

$post = $client->content()->posts()->find('my-slug', includeDynamic: true);

$hero = $client->image('11111111-1111-4111-8111-111111111111')
    ->width(1600)
    ->height(900)
    ->focus(0.52, 0.38)
    ->format('webp');

$heroUrl = $hero->url();
$heroSrcset = $hero->srcset([480, 768, 1280, 1600]);

$events = $client->content()->events()
    ->eventType('performance')
    ->visibilities(EventVisibilityType::PUBLIC)
    ->perPage(20)
    ->get();

$search = $client->content()->search()
    ->query('Jahresbericht')
    ->get();
$morePosts = $client->content()->search()
    ->query('Jahresbericht')
    ->type(SearchResultType::POST)
    ->page(2)
    ->get();

$categories = $client->newsletter()->categories();
$client->newsletter()->subscribe('guest@example.org', [$categories[0]->uuid]);
$client->newsletter()->confirm($opaqueConfirmationToken);
$client->newsletter()->requestUnsubscribe('guest@example.org');
$client->withoutUserToken()->newsletter()->unsubscribeAll($opaqueUnsubscribeToken);
```

The public subscribe and unsubscribe-request calls return a neutral status and never
expose whether the email address or subscription exists. By default, expiring signed
links sent by Membergy open its scanner-safe browser landing. A tenant-configured,
first-party landing may instead submit the opaque confirmation token from its explicit
POST handler to `confirm()` server-to-server. It may likewise submit an opaque full-
unsubscribe token to `unsubscribeAll()` from an explicit POST handler. The SDK does not
decode or cache these tokens, and neither method accepts a callback URL. A GET never
changes a subscription. Existing signed category-specific endpoints remain accepted by
Membergy for compatibility; new consumers intentionally use `unsubscribeAll()` for an
opaque guest credential and the authenticated `me()->newsletter()` self-service for
category changes.

Membergy has replaced its former `forms/register` endpoint with dedicated registration
offer and application endpoints. The SDK now provides provisional, typed
`registrationOffers()` and `registrationApplications()` resources against the captured
registration-v1 contract. See [registration usage and limits](docs/registration.md).
Backend review and an exclusive serial backend test gate are still pending; this
implementation is not yet an integrated or released compatibility guarantee.

### Authenticated per-user access

Public content requires no auth. For members-only content (CMS
visibility = `members`), obtain a versioned user bearer token and switch the client:

```php
$token = $client->auth()->tokenFromCredentials(
    'member@example.org',
    'secret-password',
    'Club website',
);
$authed = $client->withUserToken($token->accessToken);

$access = $authed->me()->access()->get();
$internalPost = $authed->content()->posts()->find('internal-only-slug');
$memberEvents = $authed->content()->events()
    ->visibilities(EventVisibilityType::PUBLIC, EventVisibilityType::MEMBERS)
    ->perPage(20)
    ->get();
$profile = $authed->me()->person()->get();
$profileForm = $authed->me()->person()->form();
$updatedProfile = $authed->me()->person()->update([
    'firstname' => 'Alex',
    'metadata' => ['shirt_size' => 'L'],
]);

$subscriptions = $authed->me()->newsletter()->get();
$authed->me()->newsletter()->replace([
    '11111111-1111-4111-8111-111111111111',
]);

$authed->auth()->revokeCurrentToken();
```

The tenant access proof accepts direct single-/multi-tenant membership or global landlord
access and does not require a person record. The token DTO exposes `emailVerified`; protected
Membergy endpoints still enforce their normal verification middleware. Accounts with confirmed two-factor authentication throw
`TwoFactorRequiredException`, because auth-v1 intentionally does not bypass an interactive
second factor. Invalid credentials, revoked tokens and validation failures map to dedicated
SDK exceptions.

Password reset reuses Membergy's existing Fortify password policy and notifications:

```php
$client->auth()->requestPasswordResetLink('member@example.org');
$client->auth()->resetPassword(
    $resetToken,
    'member@example.org',
    $newPassword,
    $newPassword,
);
```

The backend retains its pre-existing raw-string `application/json` token response for old
consumers. The SDK always opts into the structured auth-v1 vendor media type.

### Conditional caching and transport resilience

Framework-agnostic consumers can add any PSR-16 stores without changing the resource API:

```php
use Lacodix\MembergySdk\Cache\CacheOptions;

$cached = $client->withCache(
    publicStore: $publicPsr16Store,
    memberStore: $privatePerMemberPsr16Store,
    options: new CacheOptions(
        prefix: 'club-website',
        resourceTtls: ['events' => 60, 'pages' => 300],
    ),
);

$page = $cached->content()->pages()->find('willkommen');
$cached->flush('pages'); // or flush() for every resource generation
```

Only successful JSON GET/HEAD responses with explicit compatible `Cache-Control`
directives are stored. Fresh entries avoid a request; stale entries revalidate with both
`If-None-Match` and `If-Modified-Since`, and a `304` restores the cached body.
Server `no-store` always wins. Public and member keys use separate stores/scopes;
member keys contain a one-way token fingerprint, never a bearer token. Laravel leaves
member caching disabled by default.

Registration resources bypass SDK caching entirely, including private offers,
prefill, token landings and document bytes. Resume credentials are supplied to each
offer/submit request explicitly and never stored on the client or shared connector.

GET/HEAD requests retry transient network failures, 429 and 5xx with bounded exponential
backoff. Writes are never replayed. Exhausted 429 responses become
`RateLimitException` with an optional `retryAfterSeconds` value.
Registration requests make one attempt and use separate, sanitized
`RegistrationException` types, including `RegistrationRateLimitException`.

### Menus and consumer URLs

Membergy returns stable target type, UUID, slug and title. Route decisions stay in
the consuming website:

```php
use Lacodix\MembergySdk\MenuUrlResolver;

$resolver = new MenuUrlResolver([
    'page' => fn ($target) => '/'.$target->slug,
    'post' => fn ($target) => '/news/'.$target->slug,
    'post_category' => fn ($target) => '/news/category/'.$target->slug,
]);

$menu = $client->content()->menus()->find('main');
$url = $resolver->resolve($menu->items[0]->target);
```

Custom links pass through unchanged. Unknown future block and target types are retained
as raw fallbacks so additive API evolution does not break older SDK versions.

The original `$client` is untouched — `withUserToken()` returns a new
instance, so it's safe to use in Octane / long-lived processes.

## Usage (Laravel)

Install config and overridable renderer views:

```bash
php artisan membergy:install
```

Add to `.env`:

```
MEMBERGY_URL=https://membergy.app
MEMBERGY_TENANT=my-club
```

`MEMBERGY_URL` defaults to `https://membergy.app`. Point it at the reachable
local or review instance when developing a consumer application, for example
`MEMBERGY_URL=http://membergy.test`.

Then resolve the client from the container or use the facade:

```php
use Lacodix\MembergySdk\Laravel\Facades\Membergy;

$posts = Membergy::content()->posts()->get();
```

The Laravel binding is request-scoped. A token in the configured session key takes
precedence over the optional static token without leaking into Octane or later requests.
Config-sourced static tokens remain available for CMS calls; registration offer GET and
submit reject them before sending. Use `withoutUserToken()` for guests or the current
applicant's session / explicit `withUserToken()` client for account registration.
Public caching uses the configured Laravel cache store. Useful commands:

```bash
php artisan membergy:cache-clear
php artisan membergy:cache-clear pages
```

### Optional Blade renderer

The renderer covers every BlockDocument-v1 block, recursive columns, common settings and
dynamic post-category includes. Unknown future blocks are skipped. Views live under the
`membergy::` namespace and can be published/overridden without forking the SDK:

```blade
<x-membergy::content
    :document="$page->content"
    :included="$page->included"
/>

<x-membergy::menu :menu="$menu" :resolve="$menuUrlResolver" />
<x-membergy::events :events="$events->items" />
```

The SDK provides semantic `membergy-*` classes but deliberately no fixed theme.
Renderer views trust only the backend-sanitized rich-text/raw-HTML contract; unknown raw
payloads are never rendered.

See [the compatibility matrix](docs/compatibility.md) for the supported API and contract
versions.

## Testing

```bash
MEMBERGY_BACKEND_ROOT=/path/to/cms-source \
MEMBERGY_REGISTRATION_BACKEND_ROOT=/path/to/registration-source \
composer test
```

Unit tests run locally. Integration tests use Saloon's `MockClient`
to simulate HTTP responses — no running Membergy instance required. Both source
checkouts are required for the separate fixture gates.

The copied API fixtures under `tests/Fixtures/Contracts/Cms/v1` contain exactly 49
backend response fixtures plus the separate `block-registry.json`. The cross-repo
gate requires `MEMBERGY_BACKEND_ROOT` to point to the authoritative backend checkout;
a missing checkout fails the gate and is never skipped. It checks missing and extra
files in both repositories and byte-identical responses and BlockRegistry.

Registration fixtures live separately under `tests/Fixtures/Contracts/Registration/v1`:
41 captured backend responses (including the exact PDF bytes), their request/status/header
metadata, OpenAPI, JSON schema and provenance. `MEMBERGY_REGISTRATION_BACKEND_ROOT`
selects that source checkout. Its independent gate rejects missing or extra SDK files
and any byte difference; it never skips or changes the CMS gate's source.

## License

MIT — see LICENSE.md.
