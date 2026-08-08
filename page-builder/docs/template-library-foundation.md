# Page Builder — Template Library Foundation

*Phase 26.21. The **discovery and distribution** layer above `TemplateProvider`. It adds
browsing, search, filtering, categorization, and multi-source management for templates.
**No Template Picker, Apply UX, marketplace, payments, licensing, cloud sync, analytics, or
versioning are built in this phase.** This is a foundation the future Template Picker consumes.*

```
Template Sources  →  Template Library  →  (future) Template Picker  →  Template Runtime  →  Builder
```

---

## 1. Architecture position

The Template subsystem is layered. Each layer has one responsibility and never absorbs the one
below it:

```
Template Contracts        (26.16)  shape of a TemplateDefinition
        │
Template Runtime          (26.17)  resolve + apply a template's layout
        │
Template Authoring        (26.18)  capture the editor layout → a TemplateDefinition
        │
Template Management       (26.19)  rename / duplicate / delete / search (provider-only)
        │
Template Library          (26.21)  DISCOVERY: browse / search / filter / categorize / sources
```

The **Library sits above `TemplateProvider`**. It is a read-only discovery surface — it never
resolves, applies, or mutates templates.

### Responsibility boundary (do not merge)

| Layer | Owns |
|---|---|
| `TemplateProvider` | `get` · `register` · `update` · `remove` (the storage seam) |
| `TemplateLibraryProvider` | `all` · `find` · `search` · `filterByCategory` · `filterBySource` (discovery) |

Mutations still flow through `TemplateProvider` (via authoring/management). The Library only
*reads* — it surfaces whatever the underlying sources contain.

---

## 2. Metadata-first discovery

Browsing must be cheap. The Library's unit is a **`TemplateLibraryItem`** — metadata only. It
deliberately does **not** carry the layout tree; the full `TemplateDefinition` is fetched from
the `TemplateProvider` only when a template is actually applied (a future concern).

```ts
interface TemplateLibraryItem {
  id: string          // '<source>:<templateId>' — unique across the whole library
  templateId: string  // references TemplateDefinition.id within its source
  name: string
  description?: string
  category: string    // invariant KEY (defaults to 'uncategorized')
  thumbnail?: string  // a URL, never a media/attachment id
  tags?: string[]     // invariant tag keys
  source: string      // invariant source key
}
```

`toLibraryItem(def, source)` projects a `TemplateDefinition` into this shape and drops the tree.

---

## 3. Template sources

A **source** identifies where a template comes from. Source values are **runtime invariant
keys** — machine identifiers, never localized.

| Key | Status | Provider |
|---|---|---|
| `builtin` | active | `BuiltinTemplateLibraryProvider` (item-backed, seeded via `register`) |
| `user` | active | `UserTemplateLibraryProvider` (derives metadata from `templateProvider`) |
| `imported` | active | `ImportedTemplateLibraryProvider` (item-backed) |
| `marketplace` | reserved | — (future, no redesign required) |
| `remote` | reserved | — (future) |
| `cloud` | reserved | — (future) |

Reserved keys exist so future expansion needs no architectural change; no provider implements
them here.

---

## 4. Provider architecture

```
CompositeTemplateLibraryProvider          (aggregates children, discovery over the merged set)
 ├── BuiltinTemplateLibraryProvider        source = 'builtin'
 ├── UserTemplateLibraryProvider           source = 'user'   (wraps TemplateProvider)
 └── ImportedTemplateLibraryProvider       source = 'imported'
       (+ future MarketplaceProvider — added via composite.addProvider, no redesign)
```

- **`BaseTemplateLibraryProvider`** — every provider's `find`/`search`/`filterBy*` derives from
  `all()`, so behavior is identical across sources. Subclasses implement only `all()`.
- **`StaticTemplateLibraryProvider`** — holds pre-built `TemplateLibraryItem` metadata directly
  (builtin/imported). On `register` it normalizes the item to its own source: forces the source
  key, recomposes the id as `<source>:<templateId>`, passes the thumbnail through the asset URL
  guard, and defaults the category. Reads return clones (immutable metadata).
- **`UserTemplateLibraryProvider`** — derives items from a `TemplateProvider` on demand and drops
  layout trees. It never writes; renames/removes done through the provider simply surface here.
- **`CompositeTemplateLibraryProvider`** — concatenates child snapshots in child order; discovery
  runs over the merged set so a query spans every source at once.

The default composition is exported as **`templateLibrary`** (a composite over the three active
sources). A future Template Picker consumes `templateLibrary`, never a concrete provider —
exactly as the runtime consumes `templateProvider`.

---

## 5. Filtering & search

Pure functions over a passed snapshot (`templateLibraryFilters.ts`), never touching a provider:

- `filterLibraryBySource(items, source)` — exact source KEY match.
- `filterLibraryByCategory(items, category)` — exact category KEY match.
- `searchLibrary(items, query)` — case-insensitive substring over **name, description, tag
  keys**. An empty/whitespace query returns every item (a no-op filter, not a reset).
- `findLibraryItem(items, id)` — by library-wide id.
- `cloneLibraryItem(item)` — deep clone, so callers can never mutate stored/derived metadata.

---

## 6. Asset portability

Templates must remain portable across installs. Per **Phase 26.20.1**, a thumbnail is a **URL**,
never a media id, attachment id, storage path, or CDN provider metadata.

`sanitizeThumbnail` reuses the Phase 26.20 asset URL boundary (`isSafeAssetUrl`): http/https and
relative URLs pass; dangerous schemes (`javascript:`, `data:`, `file:`, `vbscript:`) and blanks
are dropped. Thumbnails are URLs by contract; the guard protects the scheme.

```json
{ "thumbnail": "https://example.com/template.webp" }
```

---

## 7. Localization boundary (Architecture Lock #2)

Never translate: **ids · template ids · source keys · category keys · provider keys · component
types · contract keys.** Only **labels, descriptions, and UI messages** are localized. A user's
template name and description are *content data*, not UI strings.

---

## 8. Future distribution foundation (not implemented)

A future export/import format — a **Template Package** — is anticipated but **not built here**:

```
Template Package
  - metadata
  - layout tree
  - assets            (URLs — portable)
  - required components
  - required tokens
  - compatibility information
  - version
```

The `imported` source and `ImportedTemplateLibraryProvider` are the seam this package will feed.
No export/import, versioning, or marketplace is implemented in this phase.

---

## 9. Regression guarantees

This phase is **purely additive** discovery. It does **not** change:

- BuilderState · CommandBus · the renderer · the Template Runtime.
- `TemplateProvider` or `LocalTemplateProvider`.
- Pattern v1 (any layer).

Full builder suite green (**1165 tests**). Library behavior is covered by
`templates/library/__tests__/TemplateLibrary.test.ts`.
