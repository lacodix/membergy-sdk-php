# Membergy SDK for PHP

PHP SDK for the [Membergy](https://members.example.org) API.

**Framework-agnostic**: works in any PHP 8.3+ project (Laravel, WordPress,
Symfony, plain PHP). Ships with an optional Laravel integration
(ServiceProvider, Facade, config publishing) that auto-activates when
Laravel is present.

## Status

Early development. Scope for v0.1:

- [x] `MembergyClient` + `MembergyConnector` (Saloon-based)
- [x] `content()->posts()` (list, find, filter, sort, paginate)
- [ ] `content()->menus()`
- [ ] `content()->images()` / `->files()`
- [ ] `content()->newsletter()` (subscribe / unsubscribe)
- [ ] `content()->boilerplates()`
- [ ] `auth()->tokenFromCredentials()` (obtain sanctum user token)
- [ ] Generic `resources()` access (Resourcerer CRUD incl. field metadata)
- [ ] Laravel integration (session-bound user token middleware)

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
    baseUrl: 'https://members.example.org',
    tenant:  'my-club',
);

$page = $client->content()->posts()
    ->category('news')
    ->sort('-published_at')
    ->perPage(10)
    ->get();

foreach ($page->items as $post) {
    echo $post->title, "\n";
}

$post = $client->content()->posts()->find('my-slug');
```

### Authenticated per-user access

Public content requires no auth. For members-only content (CMS
visibility = `members`), obtain a user bearer token (currently via
`POST /api/token`) and switch the client:

```php
$authed = $client->withUserToken($userToken);

$internalPost = $authed->content()->posts()->find('internal-only-slug');
```

The original `$client` is untouched — `withUserToken()` returns a new
instance, so it's safe to use in Octane / long-lived processes.

## Usage (Laravel)

Publish the config:

```bash
php artisan vendor:publish --tag=membergy-config
```

Add to `.env`:

```
MEMBERGY_URL=https://members.example.org
MEMBERGY_TENANT=my-club
```

Then resolve the client from the container or use the facade:

```php
use Lacodix\MembergySdk\Laravel\Facades\Membergy;

$posts = Membergy::content()->posts()->get();
```

## Testing

```bash
composer test
```

Unit tests are standalone. Integration tests use Saloon's `MockClient`
to simulate HTTP responses — no Membergy instance required.

## License

MIT — see LICENSE.md.
