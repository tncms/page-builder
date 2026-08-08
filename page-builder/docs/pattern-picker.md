# Page Builder — Pattern Picker & Insertion UX

*Phase 26.12. The first user-facing pattern workflow: browse registered patterns, filter
by category, preview metadata, and insert into the current page.*

This is a **UX layer only**, built entirely on the Phase 26.11 Pattern Library
Foundation. It adds no new pattern format, tree structure, insert pipeline, or history
system — and no marketplace, cloud library, template ecosystem, import/export, or AI. See
[pattern-library.md](pattern-library.md) for the foundation it consumes.

Module: `resources/editor/patterns/picker/`

| File | Responsibility |
|---|---|
| `patternFilters.ts` | pure category filter + metadata summaries (component count / types) |
| `patternPickerStore.ts` | picker state (registry snapshot, category filter, preview, insert) |
| `PatternCard.vue` | one pattern's metadata + insert/details intent |
| `PatternPicker.vue` | the browse surface: category chips, grid, empty state, details |

---

## 1. Pattern concept

A **pattern** is a reusable component-tree snapshot (`PatternDefinition` from 26.11):
`{ id, name, category?, description?, tree }`. The picker only *consumes* the registry
of these definitions — it never authors, renders, or transforms trees.

Categories are the fixed 26.11 vocabulary: **hero, content, cta, gallery, layout**. This
phase does not expand them.

---

## 2. Picker usage

```ts
import { usePatternPickerStore } from './patterns/picker/patternPickerStore'

const picker = usePatternPickerStore()
picker.openPicker()          // snapshot the registry into reactive state + show
picker.setCategory('hero')   // filter (null = all)
picker.select('hero.a')      // open metadata preview (no tree render)
await picker.insert('hero.a') // insert into the page (see lifecycle)
```

Mount `PatternPicker.vue` wherever the editor wants it (e.g. an Elements-panel "Patterns"
tab). It is a self-contained surface: it reads only the picker store and emits no
cross-cutting events.

- **Cards** show name, category, description, component count, and a distinct type
  summary (e.g. *Heading, Button, Image*) — all metadata, derived by `patternFilters`.
  No thumbnail renderer, no iframe, no isolated editor.
- **Empty state** (no patterns registered): *"No patterns available. Create patterns from
  the editor and they will appear here."* — no marketplace or cloud prompt.

---

## 3. Insertion lifecycle

Insertion always routes through the foundation — the picker **never** calls
`executeCommand` or mutates BuilderState, the component tree, or selection directly:

```
PatternCard "Insert"
  → patternPickerStore.insert(id)
  → insertPattern(definition, editorStore)      // Phase 26.11
  → executeCommand('insert-pattern', { sections })
  → InsertPatternCommand (server): ComponentId::regenerate() re-keys ids, selects new root
  → executeCommand mirrors selection + pushes ONE history entry
```

Guarantees, all inherited (never re-implemented):

- **One insertion = one undo entry.** Undo removes the inserted tree; redo restores the
  same tree.
- **Server re-keys ids** → no duplicate component ids; existing nesting/capability/
  validation rules are respected by the command.
- **Selection updates** to the inserted root, so the Inspector context follows and live
  preview is preserved.
- **Invalid selection does nothing** — an unknown id dispatches no command.
- **Root vs container target** is decided by the existing `insert-pattern` command, not
  the picker: with no valid host it inserts page-level sections.

---

## 4. Extension points

- **Registration**: patterns come from `patternLibraryRegistry` (26.11). Register
  definitions (e.g. captured from the current selection via `createPatternDefinition`) and
  they appear in the picker on the next `load()`/`openPicker()`. The picker adds no second
  storage layer.
- **Placement**: `PatternPicker.vue` can be dropped into any panel/tab; it has no
  hard-coded host.
- **Filtering**: `patternFilters.ts` holds the pure filter/summary helpers, reusable by
  future surfaces (search, grouping) without touching the store or components.

### Out of scope (by design)

Marketplace / selling / downloads · cloud sync / accounts / remote storage ·
import / export · AI generation or suggestions · usage analytics / popularity ranking.

The intended evolution is *Components → Patterns → Pattern Picker → (future) Template
System*; this phase completes only the Pattern Picker UX on the existing safe foundation.
