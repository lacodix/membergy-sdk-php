# Membergy SDK for PHP (`membergy-sdk-php`) – CLAUDE.md

## Was dieses Repo ist

Ein **downloadbares, framework-agnostisches PHP-SDK** (PHP 8.3+, Saloon 3), mit dem **externe
Entwickler** die **CMS-Inhalte** aus einer Membergy-Instanz für ihre eigene Webseite/​Dienste
abziehen können. Optionale Laravel-Integration (ServiceProvider, Facade, Config). **Früher
Entwicklungsstand** – Scope & Checkliste in `README.md`.

## Ökosystem – wo dieses Repo hingehört

Membergy ist ein Produkt aus mehreren Repos. Die **zentrale Landkarte und der aktuelle Stand**
liegen im Haupt-Repo `membergy`:

- **Repo-Landkarte & Datenfluss:** `/home/muellerd/workspace/lacodix/membergy/.ai/overview/ECOSYSTEM.md`
- **Stand / was steht an / wie weiter:** `/home/muellerd/workspace/lacodix/membergy/.ai/overview/STATUS.md`

Frage *„wie ist der Stand, was steht an, wie machen wir weiter"* → zuerst dort lesen.

Die Nachbarn (`/home/muellerd/workspace/lacodix/`): `membergy` (Web-App **+ API**, Source of
Truth), `membergy-app-nuxt` (Mobile-Rahmen), `membergy-website` (Marketing), `membergy-infra` (Betrieb).

## Der entscheidende Zusammenhang: das SDK bildet eine bestehende API nach

Jeder Punkt der v0.1-Checkliste (`README.md`) entspricht **1:1 einem bereits existierenden
Endpoint** der öffentlichen, tenant-bewussten CMS-API in `membergy`:

| SDK-Resource | Membergy-Endpoint (`/api/v1/tenant/{tenant}/content/...`) | Controller |
|---|---|---|
| `posts()` ✅ | `posts`, `post-categories` | `App\Http\Controllers\Cms\PostsController` |
| `menus()` ⬜ | `menus` | `…\Cms\MenusController` |
| `images()`/`files()` ⬜ | `images`, `files`, `image/{uuid}`, `file/{uuid}` | `…\Cms\ImagesController`/`FilesController` |
| `newsletter()` ⬜ | `newsletter/categories`, `newsletter/subscribe|unsubscribe` | `…\Cms\Newsletter*Controller` |
| `boilerplates()` ⬜ | `boilerplates` | `…\Cms\BoilerplatesController` |
| `auth()->tokenFromCredentials()` ⬜ | `POST /api/token` | `…\Api\AuthController` |
| generischer `resources()` ⬜ | `/tenant/{tenant}/resources/...` (Sanctum) | `…\Api\ResourceController` |

**Regel beim Bauen eines SDK-Resources:** den **realen Response-Shape** des zugehörigen
Membergy-Controllers (`/home/muellerd/workspace/lacodix/membergy/app/Http/Controllers/Cms/*`)
als verbindlichen Vertrag nehmen – nicht erraten. Member-only-Inhalte (CMS-Sichtbarkeit
`members`) brauchen ein User-Token (`withUserToken()`).

## Größerer Kontext

Dieses SDK ist der Unterbau für den geplanten **Vereins-Website-Generator**
(`membergy/docs/funktionen.md`). Reihenfolge: erst SDK-Scope vervollständigen → dann
Generator daraufsetzen. Source of Truth bleibt `membergy`.
