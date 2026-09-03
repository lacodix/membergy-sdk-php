# API/SDK compatibility

The SDK fails fast when configured for an unsupported API URL version. Additive
response fields remain forward-compatible through DTO `extra` bags and unknown block,
dynamic-include and menu-target fallbacks.

| SDK line | Membergy API | CMS | Forms | Auth | Self-service | BlockDocument | PHP |
|---|---|---|---|---|---|---|---|
| current `0.x` development line | `v1` | `cms-v1` | `forms-v1` | `auth-v1` | `self-service-v1` | schema `1` | 8.3+ |

The machine-readable equivalent is returned by:

```php
$matrix = $client->compatibility();
```

Compatibility rules:

- a new URL API version requires a new compatible SDK line;
- additive fields within an existing contract version do not require a major SDK
  release and are retained in `extra`;
- a changed meaning, removed field or changed field type requires a new contract
  version;
- BlockDocument schema changes are independent from the URL API and require a new
  schema number plus renderer support;
- the copied backend fixtures and BlockRegistry must pass the cross-repository
  byte-for-byte gate before a release.
