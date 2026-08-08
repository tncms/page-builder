# Page Builder — Pattern Authoring & Save Flow

*Phase 26.13. The Create step that completes* **Create → Register → Browse → Insert** *on
top of the Phase 26.11 foundation and Phase 26.12 picker.*

Authoring lets a user turn a selected layout component into a reusable **PatternDefinition**
and register it locally. It is **metadata creation only** — no database, API, migration,
marketplace, cloud, template engine, import/export, or version history.

Module: `resources/editor/patterns/authoring/`

| File | Responsibility |
|---|---|
| `patternValidation.ts` | pure root-type gate + metadata validation |
| `savePattern.ts` | pure: selected node + metadata → `PatternDefinition` (reuses 26.11) |
| `patternAuthoringStore.ts` | the save-flow store (selection, dialog, register, feedback) |
| `PatternSaveDialog.vue` | the metadata form |

---

## 1. Pattern concept

A **pattern** is a reusable *layout fragment* — a component subtree captured as a
`PatternDefinition` (`{ id, name, category?, description?, tree }`, Phase 26.11). Authoring
only produces and registers these; it never renders or transforms trees.

### Saveable roots

A pattern must be a layout host, so only these may be saved:

- **Section** (any section type) — used as-is
- **Container** — wrapped in a section
- **Column** — wrapped in a section + container (valid nesting)

Leaf blocks (text, button, image, …) and detached nodes are **not** saveable
(`isSupportedRoot` gates this, and the toolbar action is disabled for them).

---

## 2. Creation lifecycle

```
Select component (existing Builder selection)
  → toolbar / context "Save as Pattern"
  → patternAuthoringStore.openDialog()        // refuses an unsupported root
  → PatternSaveDialog (name*, category, description)
  → store.save()
      → validate metadata
      → buildPatternDefinition() (serialize + wrap)
      → patternLibraryRegistry.register()
      → picker.load()  → appears immediately
      → feedback: 'Pattern "…" created.'
```

Reuses the existing selection system — **no new selection state, editor mode, or
traversal engine.** The dialog binds to the store; the store reads the current selection
via `findComponentNode` (which returns a clone).

---

## 3. Serialization

`buildPatternDefinition` reuses the Phase 26.11 serializer verbatim
(`createPatternDefinition` → `serializePattern`). It **preserves** component hierarchy,
component data, responsive values, token references, style bindings, and state styles
(all live in `data`, copied verbatim), and **removes** component ids, runtime selection
state, and editor metadata. The source tree is never mutated (the node is a clone, and the
serializer clones again).

Root wrapping makes a saved layout block a valid **section-rooted** tree so the existing
`insert-pattern` command replays it unchanged:

```
section   → [section]
container → [section > container]
column    → [section > container > column]
```

Wrappers are synthetic, id-free, minimal-data nodes; the server assigns ids and the
renderer applies component defaults on insert.

---

## 4. Registry flow

`save()` registers a **new** definition with a fresh unique id
(`patternLibraryRegistry.register`). Because each save mints a new id, an existing pattern
is never accidentally overwritten (duplicate protection). The picker is refreshed so the
new pattern appears immediately.

**No storage** — the registry is in-memory (Phase 26.11). No database, API, or migration.

### Undo / redo

Saving is metadata creation only: it dispatches **no command**, mutates **no component
tree**, changes **no selection**, and creates **no undo entry**. Insertion (a separate
step) remains the only tree mutation, through the existing pipeline:

```
PatternPicker → insertPattern() → CommandBus → History → Selection
```

---

## 5. Future extension points

- **Persistence**: a host can serialize `patternLibraryRegistry.all()` to durable storage
  and re-register on boot — the authoring flow is unchanged.
- **Capture scope**: `wrapRootAsSections` is the single place that maps a selected root to
  section-rooted nodes; new saveable roots slot in here.
- **Metadata**: `patternValidation.ts` is the one source for field rules; extend it (and
  the dialog) without touching the store or serializer.
- **Placement**: `PatternSaveDialog.vue` mounts anywhere; wire a toolbar/context action to
  `store.openDialog()` and show `store.feedback` in the editor's toast surface.

### Out of scope (by design)

Database storage · cloud sync · marketplace / premium templates · template builder ·
import / export · version history.

The direction is *Component System → Pattern Authoring → Pattern Registry → Pattern Picker
→ Pattern Insertion*; this phase completes only the local authoring/save flow.
