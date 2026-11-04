# API/SDK compatibility

All SDK transport now requires a Tenant-owned CMS credential through the trailing named
`cmsToken` constructor argument or Laravel `membergy.cms_token` / `MEMBERGY_CMS_TOKEN`.
It travels only in `X-Membergy-Cms-Token`, independently of the visitor bearer. Missing
configuration fails locally, including guest auth/registration calls. No existing
constructor position changes; injected connectors own their credential. All client copies
retain it. CMS credentials never identify registration applicants or static user tokens.
HTTP 401 with the exact top-level code `cms_client_unauthenticated` maps to
`CmsAuthenticationException`, which extends `MembergyException` independently of visitor
`AuthenticationException`. Visitor 401 codes, including `cms_unauthenticated`, keep their
existing mappings. A CMS denial must not clear an otherwise valid visitor session.
Browser binary image/file delivery and credential-free media URL builders are unchanged.
Redirects/off-origin requests are blocked, debug output is suppressed, and serialization
of clients is rejected. Low-level fatal transport errors now also use the detached SDK
`TransportException`. Response metadata omits the CMS request header and sender exception
chain. Global Laravel event senders/raw Guzzle telemetry are intentionally bypassed.
Cache identities include a credential fingerprint; old entries are cold. Existing
contact GET429/POST no-retry and other transport retry policies are unchanged.

The SDK fails fast when configured for an unsupported API URL version. CMS and
self-service DTOs retain additive response fields through `extra` bags and unknown
block, dynamic-include and menu-target fallbacks.

| SDK line | Membergy API | CMS | Auth | Self-service | BlockDocument | PHP |
|---|---|---|---|---|---|---|
| current `0.x` development line | `v1` | `cms-v1` | `auth-v1` | `self-service-v1` | schema `1` | 8.3+ |

The authenticated person form remains part of `self-service-v1`.
The obsolete `forms-v1` registration contract has been removed: `registration()`
and `Compatibility::FORMS_CONTRACT` no longer exist, and `compatibility()` no longer
returns a `forms` key. The other matrix keys and contract versions are unchanged.

The provisional `registrationOffers()` / `registrationApplications()` implementation
uses a separately captured registration-v1 OpenAPI/schema and 43 real response files.
It is outside the integrated compatibility matrix above and does not add a machine-readable
matrix key. Registration DTOs expose the captured fields without retaining raw response
bags. Backend review and an exclusive serial backend test gate remain pending; any
resulting contract corrections must be incorporated before this contract is considered
integrated or guaranteed for consumers. Early `0.x` package publication does not
establish backend acceptance. See [registration usage](registration.md) for the
provisional surface and integration gates.

The machine-readable equivalent is returned by:

```php
$matrix = $client->compatibility();
```

Compatibility rules:

- a new URL API version requires a new compatible SDK line;
- additive fields within an integrated contract version do not require a major SDK
  release and are retained in `extra`;
- a changed meaning, removed field or changed field type requires a new contract
  version;
- BlockDocument schema changes are independent from the URL API and require a new
  schema number plus renderer support;
- the copied backend fixtures and BlockRegistry remain subject to separate
  cross-repository byte-for-byte checks for backend integration acceptance.

The current CMS v1 registry contains 17 block types, including `table` and the inert
editorial `member_continuation` marker. Tables preserve header/no-header semantics and
escape plain cells. Page/Post details, summaries and search hits expose server-supplied
`hasMemberContinuation` (also retained in the existing `extra` bag), defaulting to false on older responses. Authorization and the
public/private content projection remain backend-owned; the SDK does not infer access.

Native contact definition retrieval and submission are supported through
`content()->forms()`; see [contact forms](contact-forms.md). Main's original
ContactFormBlock constructor positions and legacy handle/topics hydration remain
supported with native UUID/configuration properties. Supplied malformed UUIDs fail.
The default Blade view stays an inert hook, overridable by the application.

Anchor scopes remain page/following, with omitted scope defaulting to page and inert
optional levels. Immutable render-tree context also supports published columns views
that call renderBlocks with three arguments, without static or per-request worker state.
Testimonials retain optional descriptions; missing media/file selections remain nullable.
Known `raw_html` and rich-text fields retain the existing backend-sanitized rendering
contract; unknown block types and versions remain suppressed.

All 64 current backend CMS JSON fixtures and the separate registry are byte-gated
against the authoritative checkout. The current schema family is mirrored in the existing
AnchorNavigation fixture directory and compared independently. Synthetic anchor examples
are not HTTP captures. Registration response/wire/schema parity is a separate gate and
is not implied by CMS alignment. See the README for published-view upgrade effects.

## CI and automatic releases

PRs and pushes to `main` run Composer validation, Pest, PHPStan and Pint in test
mode on PHP 8.3 / Testbench 10 and PHP 8.4 / Testbench 11, with lowest and stable
dependency resolution; Pint uses stable jobs to enforce the current formatter.
Secretless CI uses `composer test -- --exclude-group=backend-fixture-sync`:
only the real CMS fixture, CMS schema and registration cross-repository gates
are excluded; all synthetic drift tests remain. `composer test` without that filter
still requires both backend roots and fails if either is unavailable.

Only a successful complete package quality matrix on a push to `main` starts the
reusable release workflow. Package releases require no private backend credentials,
checkout or attestation. Independent backend contract review and exclusive serial
backend tests remain pending for provisional registration-v1; accepted corrections
must be incorporated before that contract is integrated or guaranteed for consumers.
The real fixture and schema gates remain available separately with authoritative
backend checkouts. Passing fixture sync does not replace backend review or runtime
acceptance. Package `0.1.0` is early development and is not a DSTG launch.

The release job runs
[`rymndhng/release-on-push-action` v0.28.0](https://github.com/rymndhng/release-on-push-action/tree/aebba2bbce07a9474bf95e8710e5ee8a9e922fe2),
pinned to `aebba2bbce07a9474bf95e8710e5ee8a9e922fe2`, with
`bump_version_scheme: minor` and `use_github_release_notes: true`, matching
laravel-model-filter's versioning behavior. It uses GitHub's `releases/latest`:
with no existing release or tags, the default first version is **`v0.1.0`**, then
**`v0.2.0`** on the next eligible minor bump. Associated PR labels `release:major`,
`release:minor` and `release:patch` override the default (in that priority order).
**`[norelease]` in the tip commit title** or a **`norelease` label on an associated PR**
suppresses publication while validation still runs. GitHub generates the release
notes; no PHP package version string is maintained.

Only publication has `contents: write` and `pull-requests: read`; package quality
has read access. Main runs and publication are serialized; superseded PR checks may
be cancelled. Failed or cancelled matrix lanes prevent publication. PRs never
release, and there is no manual release trigger. The action targets the validated
push SHA. Re-runs retain the original push SHA, so only re-run the latest main run; older re-runs could publish older code as the next version. GitHub may replace pending runs with newer pushes. If publication is
interrupted, inspect provider state before retrying. No backend deployment occurs.

## Safe high-level HTTP and transport exceptions

Non-registration Resource operations now expose only SDK `MembergyException` subtypes
for Saloon HTTP/transport failures. Generic `HttpRequestException` exposes numeric
`status` as its only server metadata; `TransportException` has no HTTP status or response.
Both use fixed messages. Mapped authentication, two-factor, own-data, validation and
rate-limit types retain their documented fields, including validation `errors` /
`errorCode`, challenge / expiry and `retryAfterSeconds`. All these resource failures
have `getPrevious() === null`. Malformed JSON error bodies retain status-based mappings;
malformed two-factor metadata falls back to the generic HTTP exception.

This is an intentional change from the pre-release high-level Saloon contract, including
`ServiceUnavailableException` on 503. Consumers must catch SDK HTTP/transport exceptions
or `MembergyException` instead. Direct `MembergyClient::connector()->send()` retains the
raw Saloon interface and can expose sensitive request/response objects and vendor chains.
Validation fields and two-factor challenges remain intentionally accessible to callers
but are redacted in PHP's native `var_dump` output. Their public typed values can still
appear in Symfony VarDumper, Laravel `dump` / `dd`, `var_export` or serializers; never log
or serialize their contents. Only parameters marked `#[SensitiveParameter]` (including
Request objects, profile value arrays and opaque newsletter tokens) are masked in default
exception traces, not protected against deliberate introspection.
Registration exceptions, wire contracts, retries and caching are unchanged.

## Additive self-service/form v1 repeater extension (2026-10-08)

The backend adds `type: repeater`, recursive typed `FormField::fields`, and nullable `max`.
`FormDefinition::repeaterRows()` preserves keys and values. `PersonProfile::contactDetails`
and `bankDetails` decode the keyed own contacts and owner-only bank object. Existing scalar
fields and update names remain unchanged. Update accepts keyed arrays or typed `ContactDetail`/
`FormRow` objects and encodes an empty contact set as `{}`. Full own IBAN is sensitive owner data;
all self-service requests remain authenticated and responses private/no-store.

The three `me/person*.json` files are copied byte-for-byte from backend HTTP captures, generated
by `SelfServiceApiTest --update-snapshots`, then verified without that flag. The 64 CMS
fixtures and separate registration fixture family remain independently synchronized.


Joining dates: `RegistrationVersion::joiningDate` is null or a typed
`RegistrationJoiningDate` with mode, default and typed value/label options. Submit optional
`desired_joining_date` using `RegistrationSubmission::fromArray()`. `RegistrationInput::joiningDate()`
checks the transport shape; the server enforces offer mode/window and the valid ticket window.
Off refuses the key including null; enabled omission/null/empty uses the submission-time default.
One wish applies to all participants. Prefill exposes `desiredJoiningDate`.


## Additive password/access self-service-v1 extension (2026-10-08)

`me()->password()->update()` returns `PasswordUpdateResult`. Closed request and success
schemas, strict unknown-field rejection and private/no-store responses are provided by the
backend. Error mappings reuse 422 validation and 429 throttling exceptions. The SDK marks
password arguments sensitive and suppresses Saloon request debugging for this operation.
`TenantAccess::managementUrl` is nullable; missing values from older backends hydrate as null.
The password operation requires the new backend route. Existing tokens are preserved and no
mail is sent. The 64 CMS fixtures (including access-management and password-updated)
remain byte-identical; registration sync is independent.


## Website-login hardening (package D, 2026-10-08)

Auth-v1 adds optional `remember` to credential issuance, `challenge`/`expires_at` to the
confirmed-2FA 409, and `POST /token/two-factor`. Successful token responses add `remember`
and `login_email`. The SDK hydrates older missing metadata as false/null; a consumer requiring
external 2FA must require the updated backend with a usable challenge. SDK and backend should
ship together for the security tightening: unmarked old tokens are now 401 and generic admin
API routes are centrally 403 for all website tokens. No full-scope integration issuer remains.
Legacy raw token responses retain their transport shape but share issuance/expiry/scope rules
and reject confirmed 2FA before issuing anything.

Self-service-v1 adds `POST /me/password/confirm`, typed `AuthResult`, and dedicated 423
`PasswordConfirmationRequiredException` for own-person data/form reads and writes. Login after
2FA, explicit confirmation and password change update the same token timestamp. No token
revocation or mail is added to password changes. Normal lifetime is sliding two hours;
remembered lifetime is absolute 30 days. These constraints are enforced by the backend hook,
not the client. First-party cookie-stateful Membergy remains supported.

64 CMS fixtures and BlockRegistry, plus the independent registration contract/fixture
family, are copied byte-for-byte from the backend. New/changed auth responses use official HTTP snapshot capture,
format-check random credentials and replace only a single scalar as documented in provenance.
The sync suite uses both backend-root environment variables and verifies without regeneration.


## One-time management login (package E, 2026-10-09)

`$client->me()->managementLogin()` returns `ManagementLogin` with a sensitive `url` and typed
`expiresAt`. Its URL and payload parameters are SensitiveParameter and its __debugInfo redacts
the URL. The request uses self-service-v1; 403/401/429 map to the existing forbidden/auth/rate
exceptions. This method needs the additive backend management-login route and ticket migration.
64 CMS fixtures plus the BlockRegistry and the independent registration provenance
are synced byte-for-byte. Capture and canonicalization remain backend-owned.

The server enforces a 60-second single-use ticket bound to user, club and issuing website token.
Real management rights, token lifetime and confirmed 2FA activation are checked again on redeem.
The new web session inherits the token confirmation time, preserving the three-hour password
gate. It replaces any existing Membergy session, including impersonation, and has one fixed club
management target. Website and Membergy logouts stay independent. Treat the URL as a credential;
never print it or enable raw response debugging. Configure upstream access logs to mask ticket paths.
