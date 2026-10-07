# API/SDK compatibility

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
uses a separately captured registration-v1 OpenAPI/schema and 41 real response files.
It is outside the integrated compatibility matrix above and does not add a machine-readable
matrix key. Registration DTOs expose the captured fields without retaining raw response
bags. Backend review and an exclusive serial backend test gate remain pending; any
resulting contract corrections must be incorporated before an integration or release
claim. See [registration usage](registration.md) for the provisional surface and gates.

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
- the copied backend fixtures and BlockRegistry must pass the cross-repository
  byte-for-byte gate before a release.
