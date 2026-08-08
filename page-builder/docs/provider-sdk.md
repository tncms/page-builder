# Provider SDK Guide (Phase 26.49B, Rules 1/5/6)

> **Status: Extension SDK v1.0 over the FROZEN platform.** This phase adds **no
> end-user functionality**. It publishes the official toolkit for building Design
> Resource providers entirely through the contracts frozen in
> [Phase 26.49A](design-resource-contract.md). The platform is **extended, never
> modified**.

The **Design Resource Provider SDK** is the supported way to add a new source of
Design Resources — Built-in, Imported, My Designs today, and Community,
Marketplace, PRO and Portable Package channels in the future. Every channel plugs
in through the same seam and presents the same surface.

Namespace: `Plugins\PageBuilder\SDK`.

---

## 1. What the SDK contains (Rule 1)

| Component | Type | Purpose |
|-----------|------|---------|
| `AbstractDesignProvider` | base class | Implements the frozen provider contract; you supply only `key()` + `provideResources()`. |
| `ProviderRegistration` | registration helper | Compliance-gates a provider, then registers it with the repository. |
| `ProviderComplianceValidator` | validator | Certifies a provider against every contract obligation (Rule 3). |
| `ComplianceReport` / `ComplianceCheck` | value objects | The certification result. |
| `ProviderTestCase` | PHPUnit base | Inherit the full compliance suite (Rule 7). |
| `ProviderAssertions` | PHPUnit trait | Reusable compliance assertions. |
| `Examples\ExampleDesignProvider` | reference | Documentation-by-code (Rule 2). |

The SDK reuses the frozen types — it introduces **no new core contracts**:
`DesignResourceProvider`, `DesignResource`, `Rid`, `DesignResourceRepository`,
`ResourceQuery`.

---

## 2. Build a provider in two methods

```php
use Plugins\PageBuilder\Repository\Rid;
use Plugins\PageBuilder\SDK\AbstractDesignProvider;

final class CommunityDesignProvider extends AbstractDesignProvider
{
    public function key(): string
    {
        return 'community'; // stable, unique, lowercase slug — also the RID source
    }

    protected function provideResources(): iterable
    {
        yield $this->makeResource(
            kind: Rid::KIND_SECTION,
            resourceId: 'hero-modern',
            name: 'Modern Hero',
            category: 'hero',
            payload: $this->renderableLayout(),   // id-free LayoutPayload
            tags: ['community', 'hero'],
        );
    }
}
```

`AbstractDesignProvider` provides everything else — `priority()` (default 50),
`isAvailable()` (default `true`), a deterministic `revision()`, uniform `find()`,
and the `makeResource()` factory that fills the RID/source/vendor consistently.

Override what your channel genuinely owns:

- a **remote** channel overrides `isAvailable()` with a health check and
  `revision()` with a cheap stored token (so it need not re-materialise);
- a channel that can resolve a RID directly overrides `find()` for O(1) lookup;
- a channel whose key and source differ overrides `source()`.

---

## 3. The six capabilities every provider exposes (Rule 6)

There are **no provider-specific APIs**. Every provider exposes exactly:

| Capability | How the SDK satisfies it |
|------------|--------------------------|
| **list** | `all()` → `provideResources()` |
| **find** | inherited uniform RID resolution (null on unknown/malformed) |
| **search** | resources flow through `Repository::query()` / `ResourceQuery::search()` |
| **availability** | `isAvailable()` at the provider; `distribution` (C8) at the resource drives the availability resolver |
| **preview** | every resource is RID-resolvable, so the one Runtime Preview route renders it |
| **metadata** | `DesignResource::toMetadata()` |

---

## 4. Isolation — what a provider must never do (Rules 5 & 10)

A provider **exposes resources only**. It must never mutate — or masquerade as —
the Repository, the Registry, the Capture Pipeline, Runtime Preview or the Builder
runtime. The compliance validator enforces this: a provider that also implements
`RepositoryContract` or `RegistryContract` fails the `isolation` check and is
refused registration.

Persistence of new content goes through the **Capture Pipeline** only — a provider
reads and serves; it never captures. See
[capture-pipeline-contract.md](capture-pipeline-contract.md).

---

## 5. Registering a provider (Rule 3)

Never write to the index directly. Certify-then-register:

```php
use Plugins\PageBuilder\SDK\ProviderRegistration;

$report = (new ProviderRegistration)->register($repository, new CommunityDesignProvider);
// throws NonCompliantProviderException if the provider fails compliance
```

A non-compliant provider **never reaches the repository**. Use
`registerIfCompliant()` for a soft, non-throwing variant that returns the report
either way.

The shipped providers register the same way in
`PageBuilderServiceProvider::registerResourceRepositoryProviders()`; a third-party
plugin registers its own provider from its own service provider without touching
platform code.

---

## 6. Reference provider

`Plugins\PageBuilder\SDK\Examples\ExampleDesignProvider` is documentation-by-code:
a complete channel exercising every capability — a free resource, a block wrapped
in a neutral section, and a capability-gated (PRO-style) full page — built solely
through the SDK. Copy it as a starting point.

See also: [provider-compliance.md](provider-compliance.md),
[provider-lifecycle.md](provider-lifecycle.md),
[provider-testing.md](provider-testing.md),
[provider-development-guide.md](provider-development-guide.md).
