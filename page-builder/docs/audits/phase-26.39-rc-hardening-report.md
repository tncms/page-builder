# Phase 26.39 — Hardening Report (Production Audit · UX · Empty States · Error Handling)

**Scope:** `plugins/page-builder/resources/editor` only. Core/runtime untouched. This report covers
Parts A (Production Audit), C (UX), E (Empty States), and F (Error Handling). Performance (B),
Accessibility (D), and Regression (G) have dedicated reports.

**Method:** five parallel read-only audits + direct verification of every actionable claim against
source before any edit. Only *verified* defects were fixed — no speculative cleanup (per phase
mandate). Two agent findings were verified as **false positives** and rejected.

---

## Part A — Production audit

| Check | Result | Evidence |
|-------|--------|----------|
| Stray `console.log`/debug helpers | **CLEAN** | 4 `console.*` calls exist; all are `import.meta.env.DEV`-guarded with `eslint-disable` (editor.ts:1085,1265; fieldEditorRegistry.ts:36; palette.ts:35). Zero unguarded. |
| `TODO`/`FIXME`/`HACK`/`XXX` | **CLEAN** | 0 in non-test code. |
| `debugger` statements | **CLEAN** | 0. |
| Boundary: `patterns/ → templates/` | **HOLDS** | 0 imports (patterns must never depend on templates). |
| Boundary: `insert/` router-only | **HOLDS** | 0 references to `executeCommand`/`CommandBus`/`applyTemplate`/`insertPattern`/`runCommand`. |
| Boundary: template discovery layers | **HOLDS** | `templates/picker`, `templates/library` dispatch no commands. |
| Dead code removal | **NONE (documented)** | See dead-code note below. |

### Dead-code note (verified, mention-only)
The architecture audit flagged three exports. Each was verified against source:
- **`utils/layout.ts:168` `canContainChildren` — NOT dead (false positive).** It is called internally
  at `layout.ts:186`. The audit confused it with the single-arg `canContainChildren` in
  `navigatorDescriptor`/`navigatorInsert`. No change.
- **`utils/layout.ts:95` `componentAncestors` — vestigial.** Only its own unit test references it, but
  it is a coherent API sibling of the *used* `componentPath`.
- **`utils/dataPath.ts:57` `unsetByPath` — vestigial.** Only its own unit test references it; it is the
  natural inverse sibling of the *used* `getByPath`/`setByPath`.

The two vestigial functions are small, pure, fully tested, and harmless. Per the stabilization mandate
(*"no speculative cleanup"*), repo audit precedent (`component-lifecycle-matrix.md` documented rather
than removed vestigial code), and the project rule *"if you notice unrelated dead code, mention it —
don't delete it,"* they are **documented, not removed** — deleting tested code for no functional
benefit is churn this phase explicitly avoids. Recommend revisiting at the 26.40 Extension API Freeze.

**Conclusion:** architecture is sound and boundaries are intact. No production-audit defect required a
code change.

## Part C / E — UX & empty states

Empty states are present and rendered across every user-facing panel (Template Picker/Management/
Workspace, Pattern Picker/Management, Insert launcher, Media Library, Inspector, Navigator, Canvas).
Search "no-results" states follow a consistent `No <entity> match…` form.

### Verified defect (fixed)
- **Pattern management showed no action feedback.** `patternManagementStore` recorded
  rename/duplicate/delete feedback (patternManagementStore.ts:117/129/148) but
  `PatternManagementPanel.vue` never rendered it, while the sibling `TemplateManagementPanel.vue:70`
  did. **Fix:** added `<p v-if="store.feedback" role="status">` + style, mirroring the template
  panel. Locked by a new test (`PatternManagement.test.ts` → "surfaces action feedback after a
  duplicate").

### Rejected finding (false positive)
- *"Template Apply dialog lacks an Applying… label."* **Not true** — `TemplateApplyDialog.vue:45`
  already renders `{{ store.applying ? 'Applying…' : 'Apply' }}`. No change.

### Observations (no change — subjective tone, not defects)
- Empty-state guidance tone varies (minimal vs actionable) across panels. Consistent enough to ship;
  a unified vocabulary pass is a future polish item, not an RC blocker.
- Delete-confirmation wording differs slightly between template and pattern dialogs; both are clear,
  correct, and reassure that inserted content is unaffected.

## Part F — Error handling & resilience

Every audited failure path keeps the Builder alive, is recoverable, and preserves user state:

| Failure path | Handling | Evidence |
|--------------|----------|----------|
| Invalid/duplicate/unsafe template package (import) | Validation-first, atomic; structured `TemplatePackageError`; no partial state | `importTemplatePackage`, `workspaceImport` |
| Template resolution (missing pattern, invalid tree) | Typed `TemplateRuntimeError` **before** any command | `resolveTemplate`, `applyTemplate` (atomic) |
| Preview render failure | try/catch → `previewError`; previous preview retained; canvas usable | `stores/editor.ts` refreshPreview |
| Unsafe/broken asset URL | Rejected at every surface (import, preview, picker confirm) → placeholder/undefined | `resolveAssetPreview`, `sanitizeThumbnail`, `assetPickerStore` |
| Provider/pack registration at boot | Guarded try/catch — editor still mounts if a pack/provider fails | `main.ts`, `workspace.ts` |
| Async store actions (save/publish/apply/command) | try/catch → error state, never throws to UI; `finally` clears loading | `saveDraft`, `publish`, `executeCommand` |

**Verified as robust, no change needed.** The drag path (`finishDrag`/`finishOutlineDrag` called via
`void`) was flagged by the error audit as a potential unhandled rejection; **verified as a
non-defect** — the only async work inside is `executeCommand`, which catches internally and returns a
boolean (never rejects). Drag & Drop is under the Architecture Lock and the risk is theoretical, so
it was left untouched (documented here as an observation).

---

## Summary of fixes applied (Parts A/C/E/F)

| # | File | Change | Severity |
|---|------|--------|----------|
| 1 | `patterns/management/PatternManagementPanel.vue` | Render `store.feedback` as `role="status"` (+ style) | HIGH (silent actions) |

All other findings were verified as already-robust, subjective, or false positives. See the
Accessibility report for the a11y fixes.
