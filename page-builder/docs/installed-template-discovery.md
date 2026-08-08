# Page Builder — Installed Template Discovery & Adoption

*Phase 26.31. An adoption-aware discovery layer between the Phase 26.30 installed pack ledger and
the existing Phase 26.21 **Template Library** (the discovery foundation). It projects what a site
has **adopted** and joins it to library metadata so installed templates are discoverable with pack
provenance and an availability indicator. **Reference-only and read-only:** it duplicates no
template definition, applies nothing, runs no command, and never touches BuilderState or the
runtime. No pack execution / marketplace / purchase / licensing / cloud.*

```
InstalledPackRegistry (26.30)  ──▶  Available Template Projection  ──▶  Template Library (26.21)  ──▶  discovery UI
Installation state is availability information — never a template copy, registry, or runtime source.
```

---

## 1. Installed projection (metadata-only)

```ts
interface InstalledTemplateItem {
  templateId: string          // references TemplateDefinition.id within its source
  source: string              // the 26.24 DEFINITION source KEY the template resolves through
  installedFromPack?: string  // the TemplateStarterPack.id it was adopted through (provenance)
}
```

`projectInstalledTemplates(packs)` projects the installed pack ledger into these references. It
maps each pack's source to its definition source (`local → user`, via `packSourceToDefinitionSource`)
and **dedupes** a template shared by several packs — first pack wins, mirroring the registry's
deduped `availableTemplateIds()`. Packs whose source has no definition source (reserved/unknown) are
skipped. It carries only references — never trees, component data, package contents, or runtime
state.

---

## 2. Adoption-aware item (joined to the library)

```ts
interface AdoptionTemplateItem extends InstalledTemplateItem {
  id: string          // '<source>:<templateId>' — matches the Template Library item id
  available: boolean  // true when the reference resolves in the Template Library
  name?: string; description?: string; category?: string; thumbnail?: string; tags?: string[]
}
```

`toAdoptionItem(installed, library)` joins one reference to its `TemplateLibraryItem` for display
metadata. The **Template Library remains the discovery foundation** — enrichment *reads* a
metadata-first snapshot; it never copies a definition. `available` is the availability indicator: a
reference the library cannot resolve stays discoverable but is marked `available: false`.

---

## 3. Provider (read-only, live)

`AdoptionDiscoveryProvider` projects the default installed ledger over the default library:

- `installedTemplates()` — the raw reference projection (deduped).
- `all()` — every adopted template, enriched + availability-tagged.
- `find(id)` / `fromPack(packId)` / `available()`.

Reads are live — newly installed packs surface immediately, with **no duplicate storage**. The
default `adoptionDiscovery` uses the global ledger + library.

Filters (`adoptionFilters.ts`) are pure: `filterAdoption` composes pack → source → available →
query (search over name/description/templateId); `adoptionPackCounts` / `adoptionSourceCounts` back
pack-relationship UI.

---

## 4. Store (editor-local, read-only)

`useAdoptionDiscoveryStore` is a reactive browse/search/filter surface over the provider — the
integration point a Picker/discovery UI consumes for installed badges, pack provenance, and
availability indicators. It reads **only** the provider: no selection, no install, no apply, no
command, no template resolution. `load()` re-reads the provider; getters expose `results`,
`installedCount`, `availableCount`, `packs`, `sources`, `noResults`.

---

## Boundaries

- **Reference-only** — no template definition is ever copied or duplicated.
- **Read-only** — no `apply`, `install`, `select`, command, or BuilderState/runtime mutation.
- The Template Library, Picker, runtime, and Builder are **unchanged**.
- Not in scope: apply-entire-pack, install/uninstall wizard, bulk creation, marketplace, purchase,
  payment, licensing, cloud sync, remote installation, automatic updates, analytics.
