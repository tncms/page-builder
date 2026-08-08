# Provider Development Guide (Phase 26.49A, Rules 10/13/16)

> **Status: FROZEN contract.** Official type:
> `Plugins\PageBuilder\Contracts\DesignResourceProvider`
> (extends the pre-existing `Repository\Contracts\DesignResourceProviderInterface`).

This is the official guide for building a Design Resource provider — the extension
seam for every current and future source: Built-in, Imported, My Designs, and the
future Community, Marketplace, PRO and Portable Package channels.

---

## 1. The one provider contract (Rule 10)

Every provider exposes **identical capabilities**. There are no provider-specific
APIs.

```php
use Plugins\PageBuilder\Contracts\DesignResourceProvider;
use Plugins\PageBuilder\Repository\DesignResource;

final class MyChannelProvider implements DesignResourceProvider
{
    public function key(): string;          // stable, unique (e.g. 'community')
    public function priority(): int;        // lower = higher precedence
    public function isAvailable(): bool;    // unavailable → skipped, not an error
    public function revision(): int;        // bumps when the resource set changes
    public function all(): iterable;        // iterable<DesignResource>
    public function find(string $rid): ?DesignResource;
}
```

Register it with the repository (never write to the index directly):

```php
$repository->registerProvider($app->make(MyChannelProvider::class));
```

The five shipped providers all implement this contract:
`LocalResourceProvider`, `StarterResourceProvider`, `PackageResourceProvider`,
`ImportedResourceProvider`, `UserDesignProvider`.

---

## 2. What a provider owns vs. must not touch (Rule 16)

A provider **owns** only how it materialises its resources (registry, package
files, network, database). It **must never** modify:

- the Repository (beyond registering itself)
- the Registry
- the Capture Pipeline
- Runtime Preview
- the Builder runtime

Extend the platform through these documented contracts — never patch them.

---

## 3. Producing resources

Return immutable `DesignResource` objects (the `DesignResourceContract`). Respect:

- **RID** (`<source>:<kind>:<resourceId>`) — globally unique, immutable, never
  reused. Pick a distinct `source` for your channel so RIDs cannot collide with
  `builtin` / `imported` / `my-designs`.
- **Payload** — an id-free, renderable LayoutPayload. Never embed runtime ids.
- **Fingerprint** — do not compute or store your own; content identity is derived
  by the Capture Pipeline / repository consumers from the normalized payload.

---

## 4. Persistence goes through Capture only (Rule 8)

A provider **reads/serves**; it does not capture. If your channel needs to persist
new content, it must route through the **Capture Pipeline** — never a second
persistence path.

---

## 5. Authorization (Rule 13)

Ownership and entitlement are verified **server-side**. Never trust client-claimed
RID / metadata / provider ownership. Surface entitlement via the resource's
`distribution` (C8) so the availability resolver can gate import/insert; it never
affects rendering.

---

## 6. Preview (Rule 11)

Do not build a preview renderer. Every resource previews through the one **Runtime
Preview** route, keyed by RID. Future caching must wrap it, never replace it.
