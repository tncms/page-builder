# Page Builder — Template Distribution Provider

*Phase 26.33. A **source-of-origin abstraction** over the existing Phase 26.21 Template Library
sources. Each distribution provider adapts one library source so templates can be browsed by *where
they are distributed from*, behind a small deterministic registry. **Read-only and additive:** it
duplicates no storage, resolves/applies nothing, runs no command, and never touches BuilderState —
the library, Picker, runtime, and Builder are unchanged. No `MarketplaceManager` /
`CommerceService` / `DistributionEngine`, no payments.*

```
Template Library sources (26.21)
   builtin ─┐
   user  ──┼─▶ LibraryBackedDistributionProvider ─▶ TemplateDistributionRegistry ─▶ discovery UX
   imported ┘        (adapts, never re-stores)          (deterministic order)
```

Distribution answers one question — "what does this source make available, and is it reachable?" —
without owning any templates. It is the seam a future marketplace/remote/cloud channel slots into
with no redesign.

---

## 1. Source model

`DISTRIBUTION_SOURCE` — invariant keys, never localized:

| Key | Status | Backing library source |
|-----|--------|------------------------|
| `builtin` | active | `builtin` |
| `local` | active | `user` *(deliberate rename — the user's own templates are the `local` channel)* |
| `imported` | active | `imported` |
| `marketplace` / `remote` / `cloud` | reserved | — (no provider) |

`ACTIVE_DISTRIBUTION_SOURCES` = `['builtin', 'local', 'imported']` (deterministic order).
`isKnownDistributionSource(key)` recognizes active + reserved keys.

---

## 2. Provider contract

```ts
interface TemplateDistributionProvider {
  source(): string                     // invariant distribution source KEY
  available(): boolean                 // is the source currently reachable?
  list(): TemplateLibraryItem[]        // immutable metadata snapshots
  find(id: string): TemplateLibraryItem | null
}
```

`LibraryBackedDistributionProvider(sourceKey, library, isAvailable?)` is the **only** adapter: `list`
/ `find` delegate straight to the wrapped `TemplateLibraryProvider` (already source-scoped, cloned,
immutable) — **no storage is duplicated**. `available` is injectable (default: always available), so
a future reachable-check for a remote/cloud channel needs no new class.

---

## 3. Registry

`TemplateDistributionRegistry` is a deterministic lookup:

- `register(provider)` — keyed by `source()`; a **duplicate source is rejected**
  (`DistributionRegistryError`), never silently overwritten.
- `get(source)` / `has(source)` / `sources()`.
- `all()` — every provider in **registration order** (deterministic).
- `available()` — only reachable sources, same order.

The default `templateDistribution` registers `builtin → local → imported` in that fixed order, so
the builtin provider always lists first. Registering a new channel is the only change a new source
requires; the contract and registry are untouched.

---

## Boundaries

- **Adapts, never re-stores** — providers delegate to existing library sources.
- **Read-only** — no resolve/apply, no command, no BuilderState/runtime mutation.
- The Template Library, Picker, runtime, and Builder are **unchanged**.
- Reserved marketplace/remote/cloud keys exist as seams, but there is **no** marketplace, product,
  purchase, payment, or licensing here.
