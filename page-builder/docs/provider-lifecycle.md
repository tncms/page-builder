# Provider Lifecycle Guide (Phase 26.49B, Rule 4)

> **Status: Extension SDK v1.0 over the FROZEN platform.**

There is **one** provider lifecycle. No alternative path is allowed.

```
Bootstrap        — a plugin's service provider boots
   ↓
Registration     — ProviderRegistration certifies (Rule 3), then registerProvider()
   ↓
Repository       — aggregates + resolves RIDs + de-conflicts (READ-oriented)
   ↓
Registry         — projects resources into the palette (presentation-oriented)
   ↓
Library          — discovery / browsing / availability badges
   ↓
Builder          — insertion clones resource payloads (fresh ids)
```

A provider **enters** at Registration and **exposes** resources; it never reaches
back up the chain to mutate the Repository, the Registry, or the Builder.

---

## 1. Bootstrap

A provider is a plain service. Bind it in your plugin's service provider and make
it available to register. Providers hold no persistence and read no global state at
construction.

```php
$this->app->singleton(CommunityDesignProvider::class);
```

## 2. Registration (the only entry point)

```php
$repository = $this->app->make(
    \Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface::class,
);

(new \Plugins\PageBuilder\SDK\ProviderRegistration)
    ->register($repository, $this->app->make(CommunityDesignProvider::class));
```

Registration is **idempotent by key** (re-registering the same key replaces the
instance) and **compliance-gated** (a failing provider is refused). This mirrors
exactly how the shipped providers register in
`PageBuilderServiceProvider::registerResourceRepositoryProviders()`.

## 3. Repository

The repository orders providers by `(priority ASC, key ASC)`, resolves each RID to
its highest-precedence owner, and caches the index — rebuilding automatically when
any provider's `revision()` changes. Conflict policy is deterministic:

- lower `priority()` wins; the earlier owner keeps the RID;
- equal `priority()` claiming an owned RID is an ambiguous collision
  (`DuplicateRIDException`).

Pick a **distinct `source`** for your channel so RIDs cannot collide across
channels in the first place.

## 4. Registry

The Builder Resource Registry projects section/block resources into the editor
palette, grouped by source. It is presentation-only — it owns no persistence and no
runtime. (Repository membership and palette membership are independent: My Designs,
for instance, is queryable in the repository but reserved from the palette.)

## 5. Library → Builder

The Library lists and previews resources (every preview through the one Runtime
Preview route, keyed by RID) and shows availability. Insertion into the Builder
**clones** a resource's id-free payload with fresh ids — the resource itself stays
immutable, and the Working Copy is always isolated from the repository payload.

---

## No alternative lifecycle

- No provider writes to the index directly.
- No provider persists content except through the Capture Pipeline.
- No provider renders its own preview.
- No second Repository or Registry may be introduced.

See also: [provider-sdk.md](provider-sdk.md),
[provider-compliance.md](provider-compliance.md),
[design-resource-contract.md](design-resource-contract.md).
