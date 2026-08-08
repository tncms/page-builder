# Page Builder — Template Pack Installation

*Phase 26.30. An **adoption** layer between Phase 26.28 starter packs and the existing Phase 26.24
template sources. Installing a pack validates availability and records a **reference** — never a
copy of a template. It makes templates available for a site; it does **not** place content in the
builder, run a command, create an undo entry, or touch BuilderState. No marketplace / purchase /
payment / licensing.*

```
Starter pack (references) ── install ──▶ validate availability (Phase 26.24 sources) ──▶ Installed ledger (references)
Definitions stay in their existing source; nothing is copied or duplicated.
```

---

## 1. What installation is (and isn't)

- **Is** — an adoption record: "this site has taken up pack X, whose templates are available."
- **Isn't** — a copy, a deployment, a second template registry, a builder mutation, or a runtime
  action. Installation never applies a template (that remains the Phase 26.23 Apply UX / 26.24
  runtime path).

---

## 2. Contracts

```ts
interface TemplatePackInstaller {
  install(packId: string): InstallationResult
}

interface InstalledPack {
  id: string          // '<source>:<packId>' — invariant installation identity
  packId: string
  source: string
  name: string
  category: string
  templateIds: string[] // REFERENCES into the existing sources — never definitions
}

interface InstallationResult {
  ok: boolean
  installed: InstalledPack | null
  alreadyInstalled: boolean  // ok + alreadyInstalled = safe idempotent no-op
  reasons: InstallationIssue[]
}
```

---

## 3. Install flow (validation-first, atomic)

`StarterPackInstaller.install(packId)`:

1. **Resolve the pack** from the Phase 26.28 starter pack registry — missing → `pack-not-found`.
2. **Idempotency** — if already installed (`<source>:<packId>`), return the existing record with
   `alreadyInstalled: true`.
3. **Map the source** to a Phase 26.24 definition source (`builtin → builtin`, `local → user`,
   `imported → imported`); an unmappable source → `unsupported-source`.
4. **Availability** — every referenced template must resolve in that source; any missing →
   `template-unavailable` and **nothing is recorded** (atomic).
5. **Record** a reference in the installed ledger — never a definition copy.

---

## 4. Installed ledger (references only)

`InstalledPackRegistry` stores availability records (ids + `templateIds`), **never definitions** —
so it is not a second template registry and duplicates no storage. Reads return immutable clones.
`availableTemplateIds()` is the deduped union of installed references — the "installed template
source" surface. `remove(id)` drops a record; the referenced templates stay resolvable in their
original source.

---

## 5. Boundaries & sources

- **Built-in packs unchanged** — the `BuiltinTemplatePackRegistry` and
  `BuiltinTemplateDefinitionSource` (26.25) are untouched; a built-in pack installs by referencing
  the templates already in the built-in source.
- **Imported / user packs** — install by referencing the imported / user definition sources; the
  seam is prepared, not extended here.
- **No copy, no runtime** — no `TemplateDefinition` is cloned; no command, undo, or BuilderState
  write occurs.

---

## 6. Regression guarantees

Additive only. It does **not** change the runtime, apply/resolve, CommandBus, BuilderState, the
renderer, the Picker (26.22), the Apply UX (26.23), the source resolver (26.24), built-in packs
(26.25), the package format (26.26/27), the starter pack registry (26.28), or pack discovery
(26.29).

Full builder suite green (**1269 tests**). Covered by
`templates/installation/__tests__/TemplatePackInstallation.test.ts`.

---

## 7. Next

A future phase can surface installed packs (and their available templates) in the Library / Picker,
and offer "apply pack" — applying each referenced template through the existing runtime — building
on this adoption ledger with no change to storage, the runtime, or the Builder.
