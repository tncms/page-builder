# Phase 26.39 — Performance Report (Part B)

**Scope:** `plugins/page-builder/resources/editor`. **Result: GREEN — no measurable defect.** Per the
mandate, only concrete/measurable issues are reportable; no premature optimization was performed.

---

## 1. Event listener lifecycle — every global listener is paired

| Owner | Target | add | remove | Cleanup hook |
|-------|--------|-----|--------|--------------|
| `EditorCanvas.vue` | document keydown/click | ✓ | ✓ | `onUnmounted` |
| `PropertyInspector.vue` | window keydown | ✓ | ✓ | `onBeforeUnmount` |
| `insert/useInsertShortcut.ts` | document keydown (Ctrl/Cmd+I) | ✓ | ✓ | `onUnmounted` |
| `PatternRuntimeStore.ts` | shortcut target | ✓ | ✓ | returned disposer |
| `OverlayManager.ts` | viewport scroll / window resize | ✓ | ✓ | `detach()` |
| `drag/DragManager.ts` | pointermove/up/cancel/keydown | ✓ | ✓ | `teardown()` (guarded by `bound`) |

**No listener leaks.** The two global keydown listeners (canvas vs inspector) are on different scopes
with clear focus-ownership; no double-trigger.

## 2. Reactive updates & watchers

- No `watch(..., { deep: true })` anywhere in the editor tree.
- No duplicate watchers on the same source; each `watch` targets distinct scalar/immediate state
  (selection, dirty flag, breakpoint, paletteToken, previewHtml).
- No unthrottled keystroke-driven watchers on large trees.

## 3. Computed / getter cost

Tree-traversing getters (`nodeTree`, `selectedComponent`, `selectedPath`, `selectedDefinition`) are
inherent to a layout editor and are keyed to `currentPayload`/selection changes — no redundant
re-traversal, no computed-on-computed amplification, no clone-on-every-access. `labelFor` is an O(1)
geometry lookup.

## 4. Deep clones

`JSON.parse(JSON.stringify(toRaw(...)))` appears in ~11 places, all in **explicit** copy / duplicate /
serialize operations (palette copy, pattern/template serialize, dataPath snapshot) — never in a getter
or a hot render path. Cosmetic duplication only; extraction is a refactor deferred (frozen phase).

## 5. Registration & mount lifecycle

- Builtin packs, starter packs, and Insert providers register **once** at boot (`main.ts`), each in a
  guarded try/catch. `registerBuilderInsertProviders()` is idempotent (`clear()` before register).
- No heavy work in `onMounted` that repeats; drag/overlay managers attach on first render and detach
  on unmount.

## 6. Lazy-loading

`InsertPanel`, `InsertSurfaces`, and `AssetSourcePicker` are eagerly mounted but `v-if`-gated (no DOM
/ compute cost while closed). Their children are light. Eager mount is the correct simplicity/perf
trade for RC; lazy-loading would add complexity without measurable benefit.

---

## Verdict

**No verified performance defect.** Post-RC monitoring suggestions (profile-first, only if users
report lag on very large trees): `buildNodeTree()` / `componentPath()` traversal cost. No change made.
