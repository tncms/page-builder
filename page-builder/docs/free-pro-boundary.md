# FREE / PRO Boundary (Phase 26.45)

FREE and PRO share one Design Library architecture. The **only** difference between
editions is which resources a given actor may import/insert. This document defines
the seam that expresses that difference.

## Rules

1. **No premium fork.** One `DesignResource`, one repository, one preview engine,
   one library UI, one import workflow. No `Pro*`/`Premium*` twins.
2. **Licensing is metadata only.** Identity is `source:kind:resourceId` and is
   identical across editions. Licensing lives in distribution metadata (C8) and
   never affects identity or rendering. Valid: `official:page:agency-home` with a
   required capability in metadata. Invalid: `premium:page:agency-home`.
3. **The repository is the single source of truth.** It returns everything that
   exists — including locked resources. Availability is resolved *above* it.

## The availability seam

```
DesignResource ──▶ ResourceAvailabilityResolver ──▶ ResourceAvailability
                          │
                          ▼
                    CapabilityGate  ──▶  cms_can(<capability>)
```

- **`CapabilityGate`** (`Library\Availability\CapabilityGate`) — the single
  touch-point with authorization. Default `CmsCapabilityGate` delegates to
  `cms_can()`. No billing, subscription, or marketplace logic lives here.
- **`ResourceAvailabilityResolver`** — reads the required capability from
  distribution metadata and asks the gate. It never mutates the resource and never
  reaches outside repository-provided metadata.
- **`ResourceAvailability`** — an immutable verdict with two orthogonal flags:
  - `available` — does the resource resolve at all? (a stale RID does not)
  - `locked` — does it require a capability the actor lacks?

  A **locked** resource is still `available`: it is listed and previewable; only
  import/insert is gated.

## Declaring an entitlement

A resource requires a capability via its distribution metadata (C8), in either form:

```php
// flat
distribution: ['requiredCapability' => 'page-builder.pro']

// nested (also carries a display tier)
distribution: ['entitlement' => ['tier' => 'pro', 'required_capability' => 'page-builder.pro']]
```

The legacy flat `entitlement` string (e.g. `'free'`, `'pro'`) is a **display label
only** and never gates access — enforcement is always capability-driven. Every
existing FREE resource declares no required capability, so its behaviour is
unchanged: always open.

## How each surface behaves

| Surface | FREE resource | PRO resource, actor lacks capability |
|---------|---------------|--------------------------------------|
| Listing | shown | **shown** (never hidden) |
| Preview | available | **available** |
| Badge | none / tier label | `🔒 <tier> required` (`data-availability="locked"`) |
| Import / Insert | offered | **locked** (`🔒 Locked`, disabled) |

## Enforcement points

1. **Display** — `DesignLibraryService::card()` asks the resolver and sets
   `locked` / `requiredCapability` on the `LibraryCard`. The Blade card keeps the
   Preview button but replaces Insert with a disabled locked control.
2. **Import (server-side)** — `FullPageImportController` refuses a locked resource
   (`403`) before the frozen `FullPageImportService` is reached. The client also
   never initiates an insert/import for a locked card, but the server is the
   authority.

## Out of scope

Marketplace, licensing/billing, subscriptions, and actual PRO resources are **not**
implemented here. This phase only added the seam so they can be added later without
touching the runtime, serialization, BuilderState, CommandBus, or the extension API.
