# Page Builder — Unified Insert Builder Integration

*Phase 26.38 (FREE v1). Wires the Phase 26.37 [Unified Insert](./unified-insert-experience.md)
foundation into the live Builder: a toolbar entry, a keyboard shortcut, and real host handlers for
all four categories. **Integration only** — it does not redesign the Builder, CommandBus, runtime,
component tree, or drag & drop, and it adds no new picker. Opening Insert never executes a command,
mutates BuilderState, changes selection, or inserts a component.*

```
Builder toolbar  [ + Insert ]   ─┐
Ctrl/Cmd + I                     ─┴▶ InsertPanel (launcher) ─ select(category) ─▶ provider.open()
                                         │
   blocks    ─▶ component palette (left panel, Blocks tab)
   patterns  ─▶ Pattern Picker overlay ─▶ insert-pattern (existing)
   templates ─▶ Template Browser overlay ─▶ usage → Apply UX ─▶ runtime (existing)
   assets    ─▶ Asset Picker (existing modal)
```

---

## 1. Toolbar entry (Part A)

`EditorTopBar.vue` gains one `+ Insert` button (`.pb-editor-btn--insert`) that calls
`useInsertPanelStore().open()`. It is the single launcher — there is no duplicate entry — and carries
`aria-haspopup="dialog"` + `:aria-expanded`. `App.vue` mounts `<InsertPanel />` (the launcher) and
`<InsertSurfaces />` (the routed overlays) once, alongside the existing `<AssetSourcePicker />`.

## 2. Provider wiring (Part B)

`registerBuilderInsertProviders()` (called once at boot in `main.ts`, fault-isolated) registers the
four providers with `editorInsertOpenHandlers()`:

| Category  | Host handler                              | Surface made visible                     |
|-----------|-------------------------------------------|------------------------------------------|
| blocks    | `useInsertSurfacesStore().focusPalette()` | Left panel → Insert view → **Blocks** tab |
| patterns  | `usePatternPickerStore().openPicker()`    | Pattern Picker overlay                    |
| templates | `useInsertSurfacesStore().openTemplates()`| Template Browser overlay                  |
| assets    | `useAssetPickerStore().openPicker()`      | Existing Asset Picker modal               |

`patterns` / `assets` reuse the Phase 26.37 defaults; `blocks` / `templates` are the host handlers
the foundation left open. No new picker is created — the overlays wrap the **existing** `PatternPicker`
and `TemplateBrowser` components in dismissible chrome.

`useInsertSurfacesStore` holds UI intent only: a `paletteToken` (bumped to reveal the palette — the
left panel `watch`es it) and a `templatesOpen` flag. It resolves nothing and touches no BuilderState.

Registration is **idempotent** (`clear()` before register) so a re-boot never trips the registry's
duplicate-key guard.

## 3. Builder UX (Part C)

`InsertPanel.vue`:

- **Active category** — the routed category stays marked (`is-active`).
- **Keyboard navigation** — roving focus across categories with Arrow keys + Home/End (`role="menu"` /
  `role="menuitem"`).
- **Escape to close** — `keydown.esc` on the dialog closes it without routing.
- **Focus restoration** — on open it records `document.activeElement` and focuses the active/first
  category; on close it restores focus to the opener (toolbar button or wherever focus was).
- **Accessible** — `role="dialog"`, `aria-modal`, `aria-label`.

## 4. Keyboard shortcut (Part D)

`useInsertShortcut()` (attached in `App.vue`) binds **Ctrl/Cmd + I**. The pure
`handleInsertShortcut(e, open)` core:

- opens the panel on a bare Ctrl/Cmd+I (ignores Shift/Alt combos),
- **respects editor focus** — no-op while an `INPUT` / `TEXTAREA` / `SELECT` / contenteditable is
  focused, so typing wins,
- only **opens** — it never inserts, resolves, or dispatches.

---

## Runtime safety

Opening Insert — from the toolbar, the shortcut, or routing a category — only flips UI state
(launcher open, a surface flag/token, an existing picker's own `open`). It never executes a command,
mutates BuilderState, changes selection, or inserts a component. Insertion happens exclusively through
the **unchanged** downstream flows (`insert-pattern`, template usage → Apply UX → runtime, the Asset
and component-palette flows), each keeping its one-command / one-undo guarantees.

## Boundaries

- Integration only — no redesign of Builder / CommandBus / runtime / component tree / drag & drop.
- No new picker implementation; overlays wrap existing components.
- No Command Palette, AI assistant, marketplace, bulk insertion, or new runtime.
- Existing insertion pipelines, undo/redo, and selection are byte-for-byte unchanged.

## Tests

`insert/__tests__/UnifiedInsertIntegration.test.ts` — host handler routing, idempotent registration,
panel routing isolation, the Ctrl/Cmd+I core (modifier/focus rules), panel escape + focus restoration
+ accessible menu, the toolbar `+ Insert` button, and the `InsertSurfaces` overlays.
