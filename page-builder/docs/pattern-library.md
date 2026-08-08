# Page Builder — Pattern Library Foundation

*Phase 26.11. A safe internal structure for saving, registering, and re-inserting
component-tree snapshots through the existing Builder pipeline.*

A **Pattern** is a reusable component-tree **snapshot** — nothing more. It is **not** a
new renderer, component system, template engine, or marketplace. The existing
architecture stays the single source of truth:

```
Component Registry → Component Tree → Property / Responsive / Token / Style / State values → Renderer
```

A pattern only **stores** that structure and **replays** it. This phase is the
*foundation*: the contract, registry, serialize/restore, and an insert seam. There is
**no marketplace UI, no premium/cloud library, no import/export service, no AI layout
generation** — all out of scope by design.

### Relationship to the existing pattern system

The editor already has a **consumption** system (`patterns/patternStore.ts`,
`patterns/types.ts`, Phase 25): it loads *pre-authored* pattern manifests from the server
(`GET /api/page-builder/patterns`) with variables, packs, and dependencies. This phase
adds the **authoring** side — capture a tree from the *current* editor and reuse it. They
are complementary and independent; this foundation adds no coupling to the consumption
store.

Module: `resources/editor/patterns/library/`

| File | Responsibility |
|---|---|
| `patternDefinition.ts` | the contract — `PatternDefinition`, `PatternTree`, snapshots, categories |
| `serialize.ts` | live section tree → id-free snapshot |
| `restore.ts` | snapshot → concrete section nodes (id-free, or fresh-id) |
| `registry.ts` | `PatternLibraryRegistry` + `createPatternDefinition` |
| `insert.ts` | `insertPattern` — replay through the existing `insert-pattern` command |
| `ids.ts` | session-unique id generation (definition ids + defensive restore) |
| `index.ts` | barrel |

---

## 1. Data contract

```ts
interface PatternDefinition {
  id: string
  name: string
  category?: string        // suggested bucket; any string allowed
  description?: string
  tree: PatternTree        // the reusable snapshot
}

interface PatternTree { sections: PatternSectionSnapshot[] }
interface PatternSectionSnapshot { type: string; data: Record<string, unknown>; blocks: PatternBlockSnapshot[] }
interface PatternBlockSnapshot   { type: string; data: Record<string, unknown> }  // container children in data.blocks
```

Intentionally small — **no** pricing, ownership, cloud sync, or analytics. Categories are
a plain vocabulary (`PATTERN_LIBRARY_CATEGORIES = ['hero','content','cta','gallery','layout']`),
not a management system.

The snapshot is **id-free by design**: the server re-keys ids on insert (see §4), so
omitting them is what guarantees no duplicate component ids on replay — and it means **no
second component format and no migration**.

---

## 2. Serialization strategy

`serializePattern(sections)` deep-clones the live tree and strips every id, following the
canonical child-storage contract (`utils/layout.ts`): a **section**'s children are its
top-level `blocks[]`; a **block**'s (container/column) children are its `data.blocks[]`.

Everything else in `data` is copied **verbatim**, so all Phase 26.6–26.10 capabilities
survive untouched:

```jsonc
{
  "padding": { "desktop": "{{spacing.lg}}", "mobile": "{{spacing.sm}}" },  // responsive + token
  "states":  { "hover": { "background": "{{color.primary}}" } }            // 26.10 style-state map
}
```

The clone is reactive-safe (`toRaw` + JSON), the same approach the clipboard/copy path
uses, and it never mutates the source payload.

---

## 3. Restore strategy

`restorePattern(tree, options?)` rebuilds the snapshot into concrete `SectionNode[]`,
deep-cloning so an inserted tree never shares references with the stored definition.

- **Default: id-free** — mirrors a layout preset / `createComponentPayload`. The server
  assigns ids on insert.
- **`{ regenerateIds: true }`** — a defensive path for callers needing a standalone valid
  tree client-side (e.g. a preview): stamps a session-unique id on every node, so a
  restored tree never carries duplicate ids either. An optional `makeId` overrides the
  factory.

---

## 4. Insert flow

`insertPattern(definition, editor)` restores the snapshot to id-free sections and
dispatches the **existing** `insert-pattern` command — the same one layout presets use:

```
insertPattern → restorePattern (id-free) → editor.executeCommand('insert-pattern', { sections })
     → InsertPatternCommand (server) → ComponentId::regenerate() re-keys ids → selects new root
     → executeCommand mirrors selection + pushes ONE history entry
```

Guarantees, all inherited from the existing pipeline (never re-implemented):

- **One command → one undo entry.**
- **Server re-keys ids → no duplicate component ids.**
- **New root selected** — `executeCommand` mirrors `result.selection.selected` into
  `selectedComponentId`.
- **No direct mutation** of BuilderState, the component tree, or Vue state.

`editor` is any object with `executeCommand` (the real editor store satisfies it), so the
seam is decoupled and unit-testable.

---

## 5. Registry & capture

```ts
const def = createPatternDefinition({ name: 'Hero A', category: 'hero', sections: [selectedSection] })
patternLibraryRegistry.register(def)
patternLibraryRegistry.byCategory('hero')   // the picker's listing/group seam
await insertPattern(def, editorStore)
```

`PatternLibraryRegistry` is **in-memory only** — no storage, no migration, no cloud sync.
A host that wants durability can serialize `all()` itself later. A future pattern picker
lists from `all()` / `byCategory()` and inserts via `insertPattern`.

---

## 6. Guarantees

- **No CMS-core changes**, **no renderer changes**, **no CommandBus replacement** (reuses
  the existing `insert-pattern`), **no storage migration**, **no marketplace / premium /
  cloud / import-export / AI** — all out of scope by design.
- Existing components are unchanged; unknown future metadata inside `data` is copied
  verbatim, so future components and fields serialize safely with no migration.
- Editing workflow (drag/drop, inspector, renderer, live preview) is untouched — this
  phase only adds new files.
