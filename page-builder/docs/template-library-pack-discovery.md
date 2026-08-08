# Page Builder — Template Library Pack Discovery

*Phase 26.29. Projects Phase 26.28 **starter packs** into discoverable library metadata — a
browse / search / filter surface for packs, parallel to template discovery. **Projection only:** it
never installs a pack, applies templates, resolves definitions, or mutates the pack registry. This
phase stops at discovery. No marketplace / purchase / payment / licensing / cloud.*

```
StarterPackRegistry (26.28)  ── project ──▶  Pack discovery items  ──▶  browse / search / filter
Templates and their definitions are never carried — only pack metadata + a template COUNT.
```

---

## 1. Projection item (metadata-only)

```ts
interface TemplatePackLibraryItem {
  id: string            // '<source>:<packId>'
  packId: string        // references TemplateStarterPack.id
  name: string
  description?: string
  category: string      // invariant KEY
  thumbnail?: string    // a URL, never a media/attachment id
  source: string        // invariant KEY (default 'builtin')
  templateCount: number // count only — never the referenced templates
}
```

`toPackLibraryItem(pack)` projects a `TemplateStarterPack` into this shape. It carries the template
**count**, never the template ids, definitions, trees, or package contents — packs become
discoverable without the library owning or duplicating any template data. The thumbnail passes the
Phase 26.21 asset URL guard.

---

## 2. Provider (read-only, live)

`StarterPackLibraryProvider` implements a `PackLibraryProvider` (parallel to the Phase 26.21
`TemplateLibraryProvider`): `all` / `find` / `search` / `filterByCategory` / `filterBySource`. It
reads the Phase 26.28 `TemplateStarterPackRegistry` **live** and projects on demand — newly
registered packs surface immediately, and there is **no duplicate storage** (the registry stays the
single source of truth). The default `packLibrary` projects the default registry.

Filters (`packDiscoveryFilters.ts`) are pure: category/source are invariant KEYS (exact match),
search is a case-insensitive substring over name + description.

---

## 3. Discovery store (integration point)

`usePackDiscoveryStore` (Pinia `pbPackDiscovery`) is the reactive browse/search/filter surface a
future Picker/discovery UI consumes: `results`, `categories`, `sources`, `isEmpty`, `noResults`,
with `query` / `activeCategory` / `activeSource`. It reads ONLY the provider. It records **no
selection**, installs nothing, applies nothing, and resolves no template.

---

## 4. Boundaries

- **Not a Library replacement** — this is a *parallel* pack-discovery surface. The existing
  template discovery (`templateLibrary`, the Picker) is untouched and continues to work.
- **Projection, not ownership** — items are projected from the registry; the pack registry (26.28)
  is unchanged and remains authoritative. No template definitions are carried.
- **Discovery only** — no install-pack, apply-pack, pack wizard, or selection. Those are future.

---

## 5. Regression guarantees

Additive only. It does **not** change the runtime, apply/resolve, CommandBus, BuilderState, the
renderer, the Picker (26.22), the Apply UX (26.23), the source resolver (26.24), built-in packs
(26.25), the package format (26.26/27), or the starter pack registry (26.28). Existing template
discovery is unaffected.

Full builder suite green (**1262 tests**). Covered by
`templates/packDiscovery/__tests__/TemplatePackDiscovery.test.ts`.

---

## 6. Next

A future phase can surface pack items in the Picker/discovery UI and add pack selection → install
(applying a pack's templates through the existing runtime) — building on this projection with no
change to the registry, the runtime, or the Builder.
