# Page Builder — Template Apply UX

*Phase 26.23. The flow that turns a Phase 26.22 Picker selection into an applied template —
**confirm → apply** — going through the Phase 26.17 runtime and nothing else. This **completes the
Template v1 lifecycle**. No marketplace.*

```
Picker selection (26.22) → Apply UX (confirm) → Runtime applyTemplateById (26.17) → insert-pattern → Builder
```

---

## 1. Scope

| Built here (26.23) | Not here |
|---|---|
| Confirmation flow for a selection | New apply mechanism (reuses the runtime) |
| Apply via the runtime | Direct BuilderState writes |
| Success / error / applying UX | Marketplace / purchase / licensing |
| Browse+apply container | Picker changes (26.22 untouched) |

**Apply goes ONLY through the runtime** — `applyTemplateById`. The UX never resolves a layout,
reimplements `insertPattern`, dispatches a command, or writes BuilderState. One apply = one
`insert-pattern` command = one CommandBus round-trip = **one undo entry** (all owned by the
runtime, from Phase 26.17).

---

## 2. Layering (intent vs mechanism)

```
Picker            expresses INTENT — a selection { templateId, source, item }
   │
Apply UX          orchestrates confirmation, then calls the runtime
   │
Runtime           applyTemplateById → resolve → insert-pattern (atomic, one undo)
```

- **`applyTemplateIntent.ts`** — `applyTemplateSelection(selection, editor, deps)` is the single
  seam from a Picker selection to the runtime. It delegates verbatim to `applyTemplateById`
  (a `TemplateLibraryItem` carries metadata only, so the definition is fetched by id inside the
  runtime).
- **`templateApplyStore.ts`** (Pinia `pbTemplateApply`) — holds the `pending` selection;
  `request` opens confirmation, `confirm` applies, `cancel` dismisses. `confirm()` is
  **fault-isolated**: a runtime failure (`template_not_found`, a resolution error) becomes an
  `error` state, never a thrown exception, and — because the runtime is atomic *before* any
  command — BuilderState and history are left untouched.
- **`TemplateApplyDialog.vue`** — shows the pending metadata (name / source / category /
  description — no serialized blocks) and Apply / Cancel. Applying delegates to the store.
- **`TemplateBrowser.vue`** — composes the 26.22 Picker with the apply flow **without modifying
  it**: it reads the picker store's `selection`, offers an Apply action when one exists, and hosts
  the dialog. The Picker stays a pure library-discovery surface.

---

## 3. Atomicity & undo

Guaranteed by the runtime, verified here:

- **Success** → exactly one `insert-pattern` command, one undo entry, selection follows the
  server-selected new root.
- **Failure** (unknown id, missing pattern) → **no command, no undo entry, no mutation**; the
  error is surfaced and the confirmation stays open for retry/cancel.

---

## 4. Localization boundary (Architecture Lock #2)

Source and category shown in the dialog are invariant **KEYS**; only their visible labels and UI
copy (titles, buttons, hints) would be localized. The template name/description are user content.

---

## 5. Regression guarantees

Purely additive UX. It does **not** change:

- The runtime (`applyTemplate` / `applyTemplateById`), the CommandBus, the renderer, or the
  `insert-pattern` pipeline.
- The Picker (26.22), the Library (26.21), or Pattern v1.
- BuilderState is written only through the runtime's single command.

Full builder suite green (**1187 tests**). Covered by
`templates/apply/__tests__/TemplateApplyFlow.test.ts`.

---

## 6. Template v1 lifecycle — complete

```
Contracts (26.16) → Runtime (26.17) → Authoring (26.18) → Management (26.19)
        → Library (26.21) → Picker (26.22) → Apply UX (26.23)
```

A user can now author a template, manage it, discover it in the library, pick it, and apply it
into a page — one editable, undoable insertion.
