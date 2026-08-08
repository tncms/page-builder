# Page Builder — Pattern Management

*Phase 26.14. Managing locally created patterns — rename, duplicate, delete, and search —
completing the lifecycle* **Create → Register → Browse → Manage → Insert.**

Management is a **UI layer over the existing registry**. It adds no new storage format,
repository, tree system, or insertion flow, and it is **not** a marketplace, cloud
library, sharing, permission, or versioning system. Everything stays local and in-memory.

Module: `resources/editor/patterns/management/`

| File | Responsibility |
|---|---|
| `patternActions.ts` | pure registry ops: validate name, rename, duplicate, delete, search |
| `patternManagementStore.ts` | list/search state + rename/duplicate/delete flows + picker refresh |
| `PatternManagementPanel.vue` | the manage list: search, category filter, per-item actions |
| `PatternRenameDialog.vue` | rename form |
| `PatternDeleteDialog.vue` | delete confirmation |

The registry (Phase 26.11) gained two additive methods used here: `update(id, patch)` and
`duplicate(id, overrides?)`. Nothing was replaced.

---

## 1. Managing local patterns

The `PatternManagementPanel` lists every registered pattern with a name, category, and
component count, plus a search box and category chips. Each row offers **Rename**,
**Duplicate**, and **Delete**. It reads and writes only the management store, which
mutates the registry and then refreshes both itself and the picker — so lists update
immediately.

All operations are **metadata only**: they never dispatch a command, mutate the component
tree, change the editor selection, or create an undo entry.

---

## 2. Rename

`store.openRename(id)` → edit → `store.confirmRename()`.

- Empty/whitespace names are rejected (validation error, dialog stays open).
- The pattern **id and tree are preserved** (`registry.update` merges the new name in
  place, keeping registry position).
- The picker reflects the new name immediately.

---

## 3. Duplicate

`store.duplicate(id)` → a new independent pattern.

- Gets a **fresh unique id** and a **deep-cloned tree** (mutating the copy never touches
  the original).
- Preserves category/description; the default name is `"<name> Copy"`.
- The original is untouched; the copy appears in the picker immediately.

---

## 4. Delete

`store.openDelete(id)` → confirm → `store.confirmDelete()`.

- **Confirmation is required** (the delete dialog).
- Removes the entry from the registry and refreshes the picker.
- **Already-inserted components are not affected.** Inserted trees are independent (the
  insert pipeline re-keyed and committed them); deleting the pattern only removes the
  reusable definition.

---

## 5. Search

`store.setQuery(q)` + `store.setCategory(cat)`. `searchPatterns` applies the category
filter, then a case-insensitive match across **name, description, and category**. When
patterns exist but none match, the panel shows a *no-results* state; with no patterns at
all it shows an *empty* state. No ranking, analytics, or recommendation (out of scope).

Categories stay the fixed vocabulary — hero, content, cta, gallery, layout — no taxonomy
management.

---

## 6. Compatibility

Insertion is unchanged and remains the only path that touches the component tree:

```
Pattern Picker → insertPattern() → CommandBus → History → Selection
```

Management runs entirely beside it: drag/drop, the inspector, responsive editing, token
binding, global styles, and state styles are untouched.

---

## 7. Future extension points

- **Persistence**: a host can serialize `patternLibraryRegistry.all()` to durable storage
  and re-register on boot; the management flow is storage-agnostic.
- **Registry**: `update`/`duplicate`/`remove` are the extension seams — new operations
  layer on the registry, not a replacement.
- **Actions vs UI**: `patternActions.ts` is pure and reusable; new surfaces (a context
  menu, bulk actions) can call it without the store or components.

### Out of scope (by design)

Database storage · API persistence · cloud sync · marketplace / premium templates ·
sharing · permissions · versioning · template builder.
