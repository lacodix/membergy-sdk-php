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
- [x] `newsletter()` (UUID categories, DOI subscribe / signed unsubscribe request)
- [x] `registration()` (tenant form definition and submission)
- [x] Typed BlockDocument v1 DTOs, `UnknownBlock`, `UnknownMenuTarget`
- [x] Configurable `MenuUrlResolver` foundation
- [x] `auth()` (credentials token, current-token revocation, password reset)
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
    ->perPage(20)
    ->get();

$categories = $client->newsletter()->categories();
$client->newsletter()->subscribe('guest@example.org', [$categories[0]->uuid]);
$client->newsletter()->requestUnsubscribe('guest@example.org');

$registrationForm = $client->registration()->form();
$client->registration()->submit([
    'salutation' => 'mr',
    'firstname' => 'Sam',
    'lastname' => 'Taylor',
    'email' => 'sam@example.org',
    'password' => 'a-secret-password',
    'password_confirmation' => 'a-secret-password',
]);
```

Both public newsletter calls return a neutral status and never expose whether the email
address or subscription exists. The expiring signed links sent by Membergy open a
browser confirmation page; only its explicit POST activates the subscription or applies
the selected all-or-category unsubscribe action.

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

$internalPost = $authed->content()->posts()->find('internal-only-slug');
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

The token DTO exposes `emailVerified`; protected Membergy endpoints still enforce their
normal verification middleware. Accounts with confirmed two-factor authentication throw
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

GET/HEAD requests retry transient network failures, 429 and 5xx with bounded exponential
backoff. Writes are never replayed. Exhausted 429 responses become
`RateLimitException` with an optional `retryAfterSeconds` value.

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
composer test
```

Unit tests are standalone. Integration tests use Saloon's `MockClient`
to simulate HTTP responses — no Membergy instance required.

The copied API fixtures under `tests/Fixtures/Contracts/Cms/v1` are byte-identical to
the backend source of truth. When both repositories are checked out, run the cross-repo
gate with:

```bash
MEMBERGY_BACKEND_ROOT=/path/to/membergy composer test
```

## License

MIT — see LICENSE.md.
