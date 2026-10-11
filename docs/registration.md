# Provisional registration-v1

This implementation follows the current backend OpenAPI, JSON schema and 43 captured
HTTP/binary response files. The backend's independent review and exclusive serial test
gate are pending. Contract changes from those gates must be incorporated before this
contract can be considered integrated or guaranteed for consumers. Early `0.x`
package releases may include this provisional implementation; package publication
does not establish backend acceptance. The existing compatibility matrix and the
removal of `forms/register` remain unchanged.

Configure `MembergyClient` with the canonical tenant public slug and the backend base
URL. Offer and document requests use that configured origin and tenant; document
downloads do not follow a response-supplied absolute URL or HTTP redirects.

```php
use Lacodix\MembergySdk\DataObjects\Registrations\RegistrationSubmission;
use Lacodix\MembergySdk\MembergyClient;

$client = new MembergyClient(baseUrl: 'https://membergy.app', tenant: 'my-club');
$offer = $client->registrationOffers()->find('membership');

// Read the published fields, options and requirements before collecting input.
$submission = RegistrationSubmission::fromArray([
    'submission_key' => $uuidV4ForThisSubmission,
    'offer_version_id' => $offer->version->id,
    'submission_ticket' => $offer->submissionTicket,
    'website' => '', // Honeypot; forward the actual submitted value.
    'email' => $applicantEmail,
    'people' => [
        ['attributes' => $personAttributes, 'contacts' => $personContacts],
    ],
    'participants' => [
        ['person_index' => 0, 'option_ids' => $selectedOptionIds],
    ],
    'proofs' => $confirmedRequirementProofs,
]);
$application = $client->registrationApplications()->submit('membership', $submission);

$document = $client->registrationOffers()->document('membership', $documentRequirementId);
$pdfBytes = $document->bytes;
```

The submission DTO checks the frozen transport shape. It does not replace the server's
published offer rules, relationship checks, locked prefill or consent validation.
`people` and `participants` contain one or two entries. Each contact has `type` and
`value`; each proof has `participant_index`, `requirement_id` and
`shown_content_hash` from the displayed requirement. `email`, `remark`, `website`,
`proofs`, contacts and option IDs keep the schema's optional-field semantics.
Unsupported fields, including payment data, are rejected. A deliberate retry must
retain the same UUID and identical submission body; a `409` requires the consumer
to reload and reconcile the offer. The SDK never retries a POST automatically.

Existing verified accounts use `auth()->tokenFromCredentials()` and the immutable
`withUserToken($token->accessToken)` client. Account offer/submit calls carry that User
Bearer. The original guest client is unchanged, and this flow never creates a User.
`registrationApplications()->find($publicId)` reads an account-owned application.

The Laravel provider marks a config-sourced `MEMBERGY_USER_TOKEN` as a static server
credential. Offer GET and submit reject that credential before sending with
`RegistrationIdentityException`, including after `withCache()`. CMS use is unchanged.
Use `withoutUserToken()` for guest registration, or the current applicant's session
token / explicit `withUserToken()` for account registration. A direct constructor
`userToken` remains explicit caller-supplied account identity; it must belong to the
current applicant. The guard does not infer the provenance of manually supplied tokens.

## Email verification and guest resume

`requestMagicLink($email)` and
`resendVerification($publicId, $submissionKey, $email)` return neutral
`RegistrationMessage` responses. Neither discloses whether an email or application
exists. No caller-supplied callback URL is accepted.

```php
$applications = $client->registrationApplications();
$verificationState = $applications->verificationLanding($verificationToken); // GET
$resumeState = $applications->resumeLanding($magicLinkToken); // GET
```

These landing GETs only inspect token usability; repeating them never consumes the
token. Invoke `verify($verificationToken)` or `resume($magicLinkToken)` only from an
explicit, confirmed POST handler. `verify()` returns a typed application receipt.
`resume()` selects `application/vnd.membergy.registration-v1+json` and exchanges the
one-time magic link for the server-returned opaque, purpose-bound context:

```php
$resume = $applications->resume($magicLinkToken);
// Discard the raw one-time link after exchange.
$offer = $client->registrationOffers()->find($resume->offerSlug, $resume);

// Build the submission from this offer's ticket/version and confirmed input.
$application = $client->registrationApplications()->submit($resume->offerSlug, $submission, $resume);
```

Store a returned `RegistrationResume` only in encrypted server-side session state,
scoped to the current applicant and tenant. Supply it explicitly to each offer/submit
call; the SDK adds `X-Membergy-Registration-Resume` to that request alone and validates
the tenant, offer and submission version binding. A User Bearer and resume context
conflict is rejected before sending. Shared connector resume headers and registration
query parameters are rejected. Do not put contexts in browser URLs, client singletons,
logs or exception metadata. The SDK does not retain or reuse a raw magic link after
exchange. Backend responses determine expiry, revocation and replay errors.

## Errors and privacy

All registration requests make one attempt, disable redirects and bypass the SDK's
response cache even when `withCache()` is configured. This includes offers, prefill,
documents and token landings; existing CMS caching is unaffected.

Resources throw `RegistrationException` subclasses: authentication (`401`), unavailable
resource (`404`), conflict (`409`), expired/revoked credential (`410`), validation (`422`)
and rate limit (`429`). Validation errors expose deduplicated, allowlisted request field
paths, including nested person attributes and contacts, participant option IDs and proofs.
Person/participant indexes are 0–1, contact indexes 0–9, option indexes 0–49 and proof
indexes 0–99. Attribute names come from the 19 fields in the captured request schema;
contact fields are `type` / `value`, participant fields are `person_index` / `option_ids`,
and proof fields are `participant_index`, `requirement_id` / `shown_content_hash`.
Structural container paths for person `attributes` / `contacts` and participant
`option_ids` are also accepted. Unknown names, wildcards, malformed or out-of-range
indexes and oversized paths are dropped; backend messages and reason codes are not
exposed. For example, a server-only age error preserves
`people.0.attributes.date_of_birth`. Rate limits expose a numeric `retryAfterSeconds`
when supplied. Exception messages do
not retain backend bodies, credentials or email addresses, and vendor transport
exceptions are not chained. Unexpected wire shapes and transport failures have separate
`RegistrationPayloadException` / `RegistrationTransportException` types. Consumers
must also redact their own HTTP instrumentation and caller arguments.

Local validation before HTTP can raise `InvalidArgumentException`, for example for a
malformed landing token, slug, identifier or submission shape. Consumers processing
browser input must handle it alongside `RegistrationException`.

Tenant password-reset routing is outside this provisional contract. Existing
`auth()` reset methods are unchanged; this package adds no tenant reset API.

## Fixture gates

The CMS gate keeps its original source and 58 responses plus BlockRegistry. The
registration gate independently checks all 43 real response files, exact binary
bytes, request/status/header metadata, schema, OpenAPI and provenance. Both gates
fail when their checkout is missing. They run separately from secretless package CI
and do not gate package releases:

```bash
MEMBERGY_BACKEND_ROOT=/path/to/cms-source \
MEMBERGY_REGISTRATION_BACKEND_ROOT=/path/to/registration-source \
vendor/bin/pest tests/Unit/ContractFixtureSyncTest.php tests/Unit/RegistrationFixtureSyncTest.php
```


The server supplies joining-date options; consumers must not calculate their own windows:

```php
$joining = $offer->version->joiningDate;
// Render $joining?->options (value/label), select $joining?->default.
$values['desired_joining_date'] = '2026-10-01'; // only when $joining is non-null
$submission = RegistrationSubmission::fromArray($values);
$client->registrationApplications()->submit('membership', $submission);
```

Store the originally shown choices with the opaque form ticket when refetching an offer on
submission: calendar changes do not change the immutable version. A choice from the valid
24-hour ticket's issue-time window stays acceptable across a month/year boundary.
