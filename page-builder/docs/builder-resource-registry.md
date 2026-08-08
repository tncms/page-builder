# Builder Resource Registry

_Phase 26.47E — the single, provider-based registry that turns Design Resources
(Sections, Blocks) into native Builder palette elements._

This implements Workflow B of the [Design Library Product Spec](design-library-product-spec.md)
(26.47C). Builder Resources become native palette elements inserted through the
**existing** insertion pipeline — no picker for routine insertion, no second
insertion engine, no runtime change.

## Architecture

```
DesignResourceRepository        (authoritative — Rule 3)
        │
BuilderResourceRegistry         (ONE registry — Rule 1)
        │  providers (Built-in, Imported, third-party, reserved)
Palette metadata (editorMetadata → builderResources)
        │
Editor palette (EditorLeftPanel) — grouped by source + category
        │
insert-pattern / insert-component  (existing commands — Rules 7/8)
```

There is exactly **one** registry (`BuilderResourceRegistry`). Providers contribute
Design Resources pulled from the authoritative repository; the registry owns
discovery: grouping, categorising, de-duplicating, availability, and caching.

## Provider model (Rules 2, 3, 14)

`BuilderResourceProvider` is the extensibility seam. Each provider maps repository
resources into one palette **group**:

| Provider | Group | Sources | Status |
| --- | --- | --- | --- |
| `BuiltInProvider` | Built-in | `builtin`, `starter` | active |
| `ImportedProvider` | Imported Designs | `imported` | active |
| _(third-party)_ | any | any | register via `registerProvider()` |
| My Designs / Marketplace / PRO | reserved keys on `PaletteGroup` | — | **reserved, not implemented** (Rule 15) |

A plugin adds a group without touching Builder code:

```php
app(BuilderResourceRegistry::class)->registerProvider($myProvider);
```

Providers **never scan files** and **never invent resources** — they read the
repository (Rule 3). Registration is idempotent by provider key (Rule 6).

## Palette shape (Rules 4, 5, 10, 12)

`editorMetadata()['builderResources']` is:

```jsonc
{
  "section": [
    { "key": "builtin", "label": "Built-in", "categories": [
      { "key": "hero", "label": "Hero", "items": [ /* PaletteItem */ ] }
    ]},
    { "key": "imported", "label": "Imported Designs", "categories": [ /* … */ ] }
  ],
  "block": [ /* same shape */ ]
}
```

- **Kinds never mix** (Rule 4): section resources appear only under `section`,
  blocks only under `block`. Kits are reserved and excluded.
- **Groups** are by source (Rule 5), ordered by provider priority then key.
- **Categories** (Rule 12) come from each resource's `category`, so any future
  source inherits the same category model automatically.
- A `PaletteItem` exposes only display + insertion metadata (Rule 10): `rid`,
  `kind`, `name`, `category`, `tags`, `source`, `group`, `version`,
  `compatibility`, `preview`, `available`, `locked`, `requiredCapability`. The
  `rid` is the public resource handle; no storage paths or package internals leak.

## Availability (Rule 9)

Each item is resolved through `ResourceAvailabilityResolver` (Phase 26.45). A
locked resource stays listed and previewable but shows a 🔒 and cannot be
inserted; an unresolved resource is hidden.

## Insertion (Rules 7, 8)

The palette carries light metadata only. On insert the editor fetches the
resource's **id-free payload** by `rid` from:

```
GET /api/page-builder/builder/resources?rid=<rid>   → { rid, kind, sections, block }
```

(gated by `page-builder.edit` + availability; read-only). It then dispatches
through the **existing** commands:

- **Section** → `insert-pattern` with the section tree.
- **Block** → `insert-component` into the current container (falls back to
  `insert-pattern` as a self-contained host section when nothing is selected).

`ComponentId::regenerate()` assigns fresh `pb-<ulid>` ids across the whole subtree
server-side, so inserted resources are ordinary, undoable elements supporting
click, drag, duplicate, delete, copy, paste, undo, and redo natively — with **no
special runtime and no second insertion engine**. Imported node ids are never
reused.

The Design Library / picker `page-builder:insert-resource` intent event (Phase
26.47B) is now consumed by `wireBuilderResourceInsertion()`, routing through the
same store path.

## Search (Rule 11)

`BuilderResourceRegistry::search($term)` matches across name / category / tags /
source for **both** built-in and imported resources — one unified search, no
separate imported search.

## Caching (Rule 13)

The computed palette is cached and rebuilt only when the contributed resource set
changes — the cache signature is derived from provider revisions (which reflect the
repository RIDs). Registering a provider, or importing / removing / updating a
Design Package, invalidates it; a routine Builder load does not rebuild.

## Files

**New (PHP)**

- `src/Builder/Registry/BuilderResourceRegistry.php` — the one registry.
- `src/Builder/Registry/BuilderResourceProvider.php` — provider interface (seam).
- `src/Builder/Registry/AbstractRepositoryResourceProvider.php` — shared base.
- `src/Builder/Registry/BuiltInProvider.php`, `ImportedProvider.php`.
- `src/Builder/Palette/{PaletteItem,PaletteCategory,PaletteGroup}.php`.
- `src/Http/Controllers/BuilderResourceController.php` — payload endpoint.

**New (JS)**

- `resources/editor/insert/builderResourceInsertion.ts` — the picker-event bridge.

**Modified**

- `PageBuilderManager::editorMetadata()` (+`builderResources`), the service
  provider (bindings + provider registration), `routes/api.php` (payload route),
  and the editor (`types.ts`, `api/client.ts`, `stores/editor.ts`,
  `components/EditorLeftPanel.vue`, `main.ts`).

## What did not change

Runtime, BuilderState, CommandBus, serialization, the Extension API,
`FullPageImportService`, Runtime Preview, the repository, and Package Sharing are
all untouched. Save as Design, My Designs, Community, Marketplace, and PRO are
**not** implemented — only their provider seam / reserved group keys exist.
