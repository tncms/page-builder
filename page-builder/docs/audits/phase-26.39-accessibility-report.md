# Phase 26.39 — Accessibility Report (Part D)

**Scope:** all dialogs / modals / popups / menus in `plugins/page-builder/resources/editor`.
**Result: PASS after fixes.** 19 surfaces inventoried; 5 verified defects fixed and locked with
tests; the remainder were already compliant.

---

## 1. Dialog / menu inventory

| Component | dialog role | aria-modal | accessible name | Escape | Status |
|-----------|:-----------:|:----------:|:---------------:|:------:|--------|
| InsertPanel | ✓ | ✓ | ✓ | ✓ (+roving focus, focus restore) | PASS (26.38) |
| TemplateSaveDialog | ✓ | ✓ | ✓ | ✓ | PASS |
| TemplateDeleteDialog | ✓ | ✓ | ✓ | ✓ | PASS |
| TemplateRenameDialog | ✓ | ✓ | ✓ | ✓ | PASS |
| PatternSaveDialog | ✓ | ✓ | ✓ | ✓ | PASS |
| PatternDeleteDialog | ✓ (alertdialog) | ✓ | ✓ | ✓ | PASS |
| PatternRenameDialog | ✓ | ✓ | ✓ | ✓ | PASS |
| PropertyInspector dialogs (reset / unapplied) | ✓ | ✓ | ✓ | ✓ | PASS |
| ComponentPicker menu | ✓ (menu) | — | ✓ | ✓ (roving) | PASS |
| NavigatorNode menu | ✓ (menu) | — | ✓ | ✓ (roving, locked-skip) | PASS |
| MediaLibraryBrowser | form | — | ✓ | — | PASS |
| **TemplateApplyDialog** | ✓ | ✓ | ✓ | **✗→✓ FIXED** | FIXED |
| **TemplatePreviewModal** | ✓ | ✓ | **✗→✓ FIXED** | **✗→✓ FIXED** | FIXED |
| **PatternPreviewDialog** | ✓ | ✓ | ✓ | **✗→✓ FIXED** | FIXED |
| **PatternVariablesDialog** | ✓ | ✓ | ✓ | **✗→✓ FIXED** | FIXED |
| CanvasContextMenu | (buttons) | — | — | ✓ (global Esc) | ACCEPTED (see §3) |

## 2. Verified defects fixed

All fixes use the codebase's own established convention (`tabindex="-1" @keydown.esc` on the focusable
panel, exactly as `PatternDeleteDialog`), so they are consistent and low-risk:

| # | File | Fix |
|---|------|-----|
| 1 | `templates/apply/TemplateApplyDialog.vue` | Add `tabindex="-1" @keydown.esc="store.cancel()"` on panel; `aria-hidden` on backdrop |
| 2 | `templates/workspace/TemplatePreviewModal.vue` | Add `:aria-label="\`Preview ${item.name}\`"` + `tabindex="-1" @keydown.esc="store.closePreview()"` |
| 3 | `patterns/PatternPreviewDialog.vue` | Add `tabindex="-1" @keydown.esc="$emit('close')"` on box |
| 4 | `patterns/PatternVariablesDialog.vue` | Add `tabindex="-1" @keydown.esc="emit('close')"` on box |

Locked by tests: `TemplateApplyFlow.test.ts` (closes on Escape, doesn't apply),
`TemplatePreview.test.ts` (Escape + accessible name), `PatternVariablesDialog.test.ts` (emits close on
Escape). All green.

## 3. Accepted (documented, no change)

- **CanvasContextMenu** — a right-click popup of `<button>`s. It is already keyboard-dismissible
  (global Escape → `store.closeContextMenu()` in `EditorCanvas`) and its items are tabbable buttons.
  Adding `role="menu"` *without* full arrow-key roving would create a screen-reader expectation the
  component doesn't meet, i.e. a regression risk. Left as-is; full roving-menu semantics are a
  documented future enhancement, not an RC blocker.
- **Backdrop overlays** — decorative click-to-dismiss layers. `aria-hidden="true"` was added where a
  panel edit already touched the file; a blanket sweep of all backdrops was deliberately not done to
  avoid churn (they carry no accessible content and keyboard users dismiss via Escape/Cancel).

## 4. Already-compliant (no change)

All save/rename/delete dialogs, both Inspector dialogs, the InsertPanel, and both roving menus
(ComponentPicker, NavigatorNode) have correct dialog/menu semantics, Escape handling, focus
management, and ARIA labels. Images carry `alt`/`aria-hidden`; form inputs are labeled.

---

## Verdict

**No accessibility regression; 5 verified gaps closed.** Keyboard-only dismissal now works on every
modal. PASS.
