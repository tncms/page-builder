# Page Builder — Template Package (Import / Export) Foundation

*Phase 26.26. A portable **transport format** for moving templates between sites and into starter
kits, plus a validation-first **export/import** seam. Imported templates land in the EXISTING
Phase 26.24 imported source and Phase 26.21 imported library, then resolve and apply through the
unchanged runtime — one command, one undo. **No marketplace, storage, payments, downloads,
licensing, ownership, or analytics.***

```
Definitions ── export ──▶  Template Package (JSON)  ── import (validate) ──▶  ImportedTemplateDefinitionSource (26.24)
                                                                          └▶  imported Template Library (26.21)
                                                       │
                                          Resolve ─────┴────▶ runtime applyTemplate ─▶ Builder
```

---

## 1. Package format (transport, not runtime)

A package **wraps** definitions; it never replaces `TemplateDefinition` (the runtime keeps using
that).

```ts
interface TemplatePackage {
  schema: string      // 'tncms.template-package' — invariant KEY
  version: string     // '1.0' — invariant
  metadata: { id: string; name: string; description?: string; author?: string }
  templates: TemplateDefinition[]
}
```

`schema` and `version` are invariants used to gate compatibility — never localized. Metadata is
minimal and portable: no ownership, licensing, or pricing fields.

---

## 2. Export

`exportTemplatePackage(templates, metadata)` wraps definitions in the envelope and **normalizes**
each:

- **JSON-safe** — deep-cloned; no functions / undefined / class instances.
- **Id-free** — every component/runtime id is stripped from the tree (the server regenerates ids on
  apply, so carrying them would only risk collisions). The template's OWN id (its namespace
  identity) is kept.
- **Portable data preserved verbatim** — responsive values, tokens (`{{color.primary}}`), style
  bindings, state styles, and asset **URLs** all survive unchanged.

`serializeTemplatePackage` / `parseTemplatePackage` turn a package into / from a JSON string (parse
throws `TemplatePackageError` on malformed JSON). Export is pure — it touches no provider, runtime,
or BuilderState.

---

## 3. Import (validation-first, atomic)

`importTemplatePackage(pkg, { sink?, publishToLibrary?, validate? })`:

1. **Validate** the whole package (`validateTemplatePackage`) — nothing is registered until it
   passes. Any problem throws `TemplatePackageError` with the issue list.
2. **Normalize** every template (id-free, JSON-safe).
3. **Collision check** — reject ids already imported, before registering any (all-or-nothing).
4. **Register** definitions into the Phase 26.24 `ImportedTemplateDefinitionSource` (source =
   `imported`) and (default) **publish** metadata to the Phase 26.21 imported library.

From there, imported templates resolve via the 26.24 `TemplateSourceResolver` and apply via
`applyTemplateFromSource(id, 'imported', …)` — **one command, one undo**, exactly like built-in and
user templates. No new resolver, no duplicate storage, no runtime change.

### Validation checks

- **Envelope** — `schema` and `version` supported; metadata has an id + name; at least one
  template; no duplicate template ids.
- **Per template** — id / name / inline-tree layout; component compatibility (reuses the Phase
  26.25 `collectTreeIssues` — types + `canContain` nesting, permissive without metadata).
- **Asset boundary (Phase 26.20.1)** — the thumbnail `preview` must be a safe URL; and the template
  must carry **no storage identity** (`media_id`, `attachment_id`, `storage`, `disk`, … are
  rejected). Templates transport URLs, never ids or storage references.

---

## 4. Boundary

Import treats a package as **untrusted external input**: it is validated and normalized at the
boundary, never trusted as-is. Storage identity is stripped/rejected so nothing site-specific
leaks across installs. Definitions enter only through the imported source — the import never writes
to the user `TemplateProvider` or to BuilderState.

---

## 5. Regression guarantees

Additive only. It does **not** change the runtime, CommandBus, BuilderState, renderer, the Picker
(26.22), the Apply UX (26.23), the source resolver (26.24), the built-in packs (26.25), or Pattern
v1. Imported templates reuse every existing seam.

Full builder suite green (**1234 tests**). Covered by:
- `TemplatePackageExport.test.ts` — envelope, id-stripping, URL/token preservation, JSON round-trip.
- `TemplatePackageImport.test.ts` — validation, asset boundary, atomic import, resolve + apply.

---

## 6. Lifecycle complete

```
Author → Manage → Library → Package (export)  ─┐
                                                ├─▶  Package (import) → Imported source + library
Built-in packs ────────────────────────────────┘
                              → Picker → Apply UX → Source resolver → Runtime → Builder
```

Templates can now be authored, managed, discovered, shipped as built-in packs, and exchanged as
portable packages — all resolving and applying through one runtime path. A future
marketplace/remote/cloud layer plugs into the same imported-source + package seams with no rewrite.
