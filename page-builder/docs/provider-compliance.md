# Provider Compliance Guide (Phase 26.49B, Rules 3/10)

> **Status: Extension SDK v1.0 over the FROZEN platform.**

Compliance is how the platform guarantees that **every** provider — shipped or
third-party, FREE or PRO — presents the identical, contract-honouring surface.
`ProviderComplianceValidator` is the single validator; `ProviderRegistration`
refuses to register anything that does not pass it.

---

## 1. The checks

`ProviderComplianceValidator::validate()` returns a `ComplianceReport` — one
`ComplianceCheck` per obligation. A provider is compliant only when **every** check
passes.

| Check | Verifies |
|-------|----------|
| `key` | non-empty lowercase slug, stable across calls, not a reserved/taken key |
| `priority` | a stable, non-negative integer precedence |
| `revision` | a stable, non-negative monotonic token while the set is unchanged |
| `availability` | `isAvailable()` returns a stable boolean |
| `all` | yields `DesignResource` objects only, with valid, unique RIDs |
| `find` | round-trips `all()`; returns `null` (never throws) for unknown/malformed RIDs |
| `search` | resources are queryable & searchable through the repository |
| `preview` | every resource is RID-resolvable with a non-empty payload |
| `metadata` | every resource exposes `rid, source, kind, resourceId, name` |
| `isolation` | the provider is **only** a provider (never also Repository/Registry) |

Reserved keys (owned by shipped providers): `local`, `starter`, `package`,
`imported`, `my-designs`. Pass extra taken keys via the `$reservedKeys` argument to
reject collisions with an already-populated repository.

---

## 2. Reading a report

```php
$report = (new ProviderComplianceValidator)->validate($provider);

$report->passed();      // bool — all checks passed
$report->failures();    // array<ComplianceCheck> — only the failed checks
$report->summary();     // single-line, e.g. "Provider 'x' failed compliance — key: ..."
$report->toArray();     // structured: { provider, passed, checks[] }
```

---

## 3. Compliance-gated registration (Rule 3)

```php
use Plugins\PageBuilder\SDK\ProviderRegistration;
use Plugins\PageBuilder\SDK\Exceptions\NonCompliantProviderException;

try {
    (new ProviderRegistration)->register($repository, $provider);
} catch (NonCompliantProviderException $e) {
    report($e->report->summary()); // the provider never touched the repository
}
```

**A failing provider does not register.** This is the platform's structural
guarantee: an invalid channel cannot corrupt the index, the palette, or preview.

---

## 4. Forbidden extensions (Rule 10)

The SDK **rejects** any provider that attempts to:

- replace the Repository (implements `RepositoryContract`) → fails `isolation`;
- replace the Registry (implements `RegistryContract`) → fails `isolation`;
- replace the Capture Pipeline or Runtime Preview — there is no registration seam
  for these, so a provider simply cannot; persistence routes through Capture only;
- mutate a payload or bypass the Working Copy — `DesignResource` is immutable
  (readonly RID / fingerprint / payload), so a provider physically cannot mutate
  captured content; it can only *offer* resources.

These are not policy reminders — they are enforced by the contract types and the
`isolation` check. See [design-resource-contract.md](design-resource-contract.md).

---

## 5. Certifying the shipped providers

The same validator certifies the five shipped providers and the
`ExampleDesignProvider`. There is one bar; everyone clears it.

See also: [provider-sdk.md](provider-sdk.md),
[provider-testing.md](provider-testing.md),
[provider-lifecycle.md](provider-lifecycle.md).
