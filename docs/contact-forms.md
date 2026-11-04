# Native CMS contact forms

`$client->content()->forms()` exposes native CMS forms by UUID. It is separate from the generic person form and registration APIs.

Configure the server-only `cmsToken` (`membergy.cms_token` / `MEMBERGY_CMS_TOKEN`) on the client first. Both definition and submission requests send `X-Membergy-Cms-Token`; an optional visitor bearer remains independent. `withoutUserToken()` keeps the CMS credential. No credential belongs in the rendered form or its URLs.

```php
use Lacodix\MembergySdk\DataObjects\ContactFormSubmission;

$form = $client->content()->forms()->find($formUuid);
// Render enabled fields and keep the response/ticket private with no-store.
// Wait at least $form->minimumSeconds before an explicit visitor submission.
$ack = $client->content()->forms()->submit($form->uuid, new ContactFormSubmission(
    submissionTicket: $form->submissionTicket,
    email: 'visitor@example.test',
    subject: 'Question',
    message: 'Please contact me.',
    contactReason: 'membership', // Only when enabled; use a configured option value.
    website: '',
));
```

`find(string $uuid): ContactFormDefinition` returns `uuid`, `name`, `definition`, `submissionTicket`, `minimumSeconds`, `expiresAt` (`DateTimeImmutable`) and `extra`. The dedicated `ContactFormSchema` has `schemaVersion`, `fields` and `messages`. Each `ContactFormField` has `key`, `type`, `label`, `required`, `enabled` and ordered `options`. Each option string is both the label and submitted value; send it unchanged, including any `=` character. `ContactFormMessages` has `success` and `error`. All response DTOs preserve unknown properties in `extra`.

`submit(string $uuid, ContactFormSubmission $submission): ContactFormAcknowledgement` accepts only HTTP 202 with `status=accepted`. The acknowledgement has `status`, `message` and `extra`; acceptance means an attempt was queued, not that mail was delivered.

`ContactFormSubmission` takes `submissionTicket` and nullable `email`, `subject`, `message`, `contactReason`, `website`. Null omits a key; empty strings are retained. It validates transport bounds, UTF-8, controls, a single email and the empty honeypot, and bounds the encoded JSON to 20,000 bytes. Invalid local input throws `InvalidArgumentException` with a fixed message. The backend validates configured required fields, enabled reasons/options, ticket version, age, expiry and replay. No recipient or arbitrary input fields are accepted by the typed SDK payload.

Both operations bypass the SDK response cache and send `Cache-Control: no-store`. Contact definition GET surfaces HTTP 429 immediately as `RateLimitException` with `retryAfterSeconds`; it never retries a rate-limited response. Fatal transport errors and HTTP 5xx retain the configured connector retry policy (by default three total attempts, with 200 ms then 400 ms exponential delays). Other HTTP 4xx responses are not retried. POST never retries any outcome, including when the response is lost. Retry behavior for other SDK resources is unchanged. Handle an ambiguous POST failure explicitly and fetch a fresh ticket before a visitor retries. A 503 may leave the original ticket consumed. Clients created with `withUserToken()` or `withoutUserToken()` preserve the original client's identity.

Failures use the standard SDK exceptions: 422 `RequestValidationException` (`errors`, `errorCode`), 429 `RateLimitException` (`retryAfterSeconds`), 404 `ResourceNotFoundException`, 401 with top-level code `cms_client_unauthenticated` `CmsAuthenticationException`, visitor 401 `AuthenticationException`, other HTTP failures including 403/503 `HttpRequestException` (`status`), and connection failures `TransportException`. A rejected CMS credential must not expire a valid visitor session. Malformed successful payloads throw `HydrationException`. Exceptions do not chain vendor exceptions. Contact diagnostic hooks and native object dumps redact ticket/payload data; public DTO properties and serialization still contain it and must not be logged.

`ContactFormBlock` hydrates nullable `form_uuid` as `formUuid`; legacy `formHandle`, `topics` and the original constructor arguments remain available. Optional `definitionVersion`, `submitLabel`, `title` are also supported, with unknown block data in `dataExtra`. The current committed backend block schema declares only `form_uuid`; these optional properties do not require a pinned historical definition.

The default `membergy::blocks.contact-form` template is an inert placeholder and uses the UUID (or legacy handle) in `data-membergy-form`. Override that view or a consumer component to fetch the definition, render fields and implement the consumer's own action with CSRF protection and no-store responses. The SDK defines no browser route, form action or framework controller and makes no request during block rendering. Existing published application templates stay consumer-owned.
