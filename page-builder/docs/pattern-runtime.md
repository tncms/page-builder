# Page Builder — Pattern Runtime & Provider Seam

*Phase 26.15. The final foundation work before Template Foundation: a provider seam plus
runtime/editor polish. After this phase the Pattern subsystem is feature-complete for
local usage —* **Create → Register → Browse → Manage → Insert.**

Nothing in the data model changed. `PatternDefinition`, `serializePattern`,
`insertPattern`, the registry, and the CommandBus/undo pipeline are all untouched.

---

## Part A — The PatternProvider seam

Previously the UI stores talked to `patternLibraryRegistry` directly. Now they depend on
a small **contract** so a future provider (database, theme, plugin) can slot in without
touching the UI.

```ts
interface PatternProvider {
  all(): PatternDefinition[]
  get(id): PatternDefinition | undefined
  register(pattern): PatternDefinition
  update(id, patch): PatternDefinition | undefined
  duplicate(id, overrides?): PatternDefinition | undefined
  remove(id): boolean
}
```

Module: `resources/editor/patterns/provider/`

| File | Responsibility |
|---|---|
| `PatternProvider.ts` | the interface (six registry operations) |
| `LocalPatternProvider.ts` | a thin delegate to `patternLibraryRegistry` — no logic, no persistence, no cache, no API |
| `index.ts` | exports `patternProvider` — the active provider the UI consumes |

**This is only a seam** — deliberately *not* a `PatternRepository` / `PatternManager` /
`PatternService` / `PatternGateway` / `PatternBus` / storage hierarchy.

### Consumers

Authoring, Management, and the Picker (and `patternActions`) import `patternProvider` and
never reference the registry directly. To introduce another provider later, change the one
binding in `provider/index.ts` — no UI edits.

```
                 patternLibraryRegistry
                          ▲
                 LocalPatternProvider   (patternProvider)
                          ▲
        Authoring · Management · Picker · patternActions
```

---

## Part B — Runtime & UX polish

Module: `resources/editor/patterns/runtime/`

| File | Responsibility |
|---|---|
| `PatternRuntimeStore.ts` | recently created/used (in-memory), keyboard shortcut |
| `PatternQuickActions.vue` | reusable Insert / Rename / Duplicate / Delete bar |

### Recently Created / Recently Used

Two **in-memory-only** lists (`recentlyCreatedIds`, `recentlyUsedIds`) — no persistence,
no analytics, reset on reload. Ids resolve to live definitions through the provider on
read, so the lists reflect renames and drop deleted patterns (on recompute). Wired into
the existing flows:

- **Recently Created** ← authoring `save()` and management `duplicate()`
- **Recently Used** ← picker `insert()`

Each list is most-recent-first, de-duplicated, and capped at `RECENT_LIMIT` (10).

### Keyboard shortcut

`Ctrl/Cmd + Shift + P` opens the **existing** picker (never a second one).

```ts
// wire once from the editor host:
const dispose = registerPatternShortcuts() // defaults to window
```

`handleShortcut(event)` on the runtime store contains the logic and returns whether it
handled the event, so a host can decide about propagation.

### Quick actions

`PatternQuickActions.vue` renders Insert / Rename / Duplicate / Delete for a pattern and
routes each to the **existing** flow (picker insert; management dialogs). No new logic or
pipeline. Buttons carry `data-action` and `aria-label`s.

### Better empty state

The picker's empty state now reads: *"No patterns yet. Save a layout to create your first
reusable pattern."*

### Accessibility

- Dialogs keep `role`/`aria-modal`/`aria-label` and now close on **Escape**.
- Quick-action buttons have `aria-label`s and a visible focus ring.

---

## Compatibility

Unchanged: `insertPattern`, `serializePattern`, `PatternDefinition`, the CommandBus,
undo/redo, BuilderState, responsive editing, token binding, and style states. Management
and runtime remain metadata operations — no command, no undo entry. Insertion is still the
only tree mutation, through the existing pipeline.

---

## Future extension points

- **New provider**: implement `PatternProvider` (e.g. a database- or theme-backed one) and
  export it as `patternProvider`. The UI, authoring, management, and runtime are unchanged.
- **Persistence**: a provider is the natural home for it — the seam exists precisely so
  storage can be added without a UI refactor. This phase adds none.
- **Runtime surfaces**: `PatternQuickActions` and the recent lists are reusable building
  blocks for future panels.

This completes Pattern v1 and prepares the subsystem for the future Template Foundation
without architectural refactoring.
