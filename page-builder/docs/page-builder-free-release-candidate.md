# Page Builder FREE v1 — Release Candidate

**Phase 26.39 — Production Hardening & Release Candidate.** Status: **RELEASE CANDIDATE**.

This document is the single entry point for the FREE Page Builder at RC. It is a stabilization
deliverable, not a feature release: no new Builder capability was introduced. It records the
architecture, the module map, extension points, user/developer workflows, known constraints, and
the RC checklist. Detailed audit evidence lives under [`docs/audits/`](./audits/).

- **Tests:** 161 files / **1351** passing (was 1347 at 26.38; +4 RC regression tests).
- **Bundles:** editor, frontend-shell, frontend-iframe, workspace — all build clean.
- **Frozen core untouched:** no runtime / CommandBus / BuilderState / serialization change.

---

## 1. Architecture overview

The Page Builder is a Vue 3 + Pinia + TypeScript editor over a PHP (Laravel/Filament) component
runtime. Editing never mutates layout directly: every structural change goes through **one command**
to the server command pipeline, producing **one undo entry**. Discovery/UX layers (pickers,
libraries, the Insert router) are strictly read-only and hand off to that single pipeline.

```
                 ┌────────────────────────────────────────────────────────┐
   User surfaces │  Toolbar · Canvas · Navigator · Inspector · Insert       │
   (Vue)         │  Pattern/Template/Asset pickers · Workspace              │
                 └───────────────┬────────────────────────────────────────┘
                                 │  intent (never mutates state directly)
                 ┌───────────────▼────────────────────────────────────────┐
   Editor store  │  stores/editor.ts — selection, drag, history, preview   │
   (Pinia)       │  executeCommand() → the ONE command boundary            │
                 └───────────────┬────────────────────────────────────────┘
                                 │  runCommand(payload, command, params)
                 ┌───────────────▼────────────────────────────────────────┐
   Runtime (PHP) │  CommandBus · BuilderState · Component Runtime/Registry  │
                 │  serialization · undo/redo                               │  ← FROZEN
                 └─────────────────────────────────────────────────────────┘
```

**Invariants (verified in RC audit):**
- One structural mutation = one command = one undo entry.
- Discovery layers (Pattern Picker, Template Library, Insert router, Asset picker) never dispatch a
  command, resolve a tree, or touch BuilderState.
- Layout serialization is owned by the runtime; the client never re-implements it.

## 2. Module map

`plugins/page-builder/resources/editor/` (non-test LOC):

| Module | LOC | Responsibility | Boundary |
|--------|----:|----------------|----------|
| `stores/` | 1400 | `editor.ts` (the command boundary, selection, drag, history, preview) + `navigatorLocks.ts` | Only layer that calls `executeCommand` |
| `components/` | 6046 | Canvas, Navigator, Inspector, Top bar, overlays, context menu | UI; delegates all mutation to the store |
| `templates/` | 6174 | Template foundation → library → picker → apply → authoring → management → packs → distribution → workspace | Discovery/apply; apply routes through `insert-pattern` |
| `patterns/` | 2561 | Pattern library → picker → authoring → management → runtime | Never imports `templates/` |
| `insert/` | 665 | Unified Insert router (blocks/patterns/templates/assets) | Router-only; no runtime/command/BuilderState |
| `assets/` | 648 | URL-based media/asset picker + preview + safety | Stores plain URLs only |
| `drag/` | 797 | Pointer drag managers (visual + outline) | Listeners paired with teardown |
| `geometry/` | 624 | Overlay geometry / measurement | — |
| `utils/` | 1079 | Palette, navigation, serialization helpers | — |
| `api/` | 144 | Command/draft/publish client | Single HTTP surface |

## 3. Extension points

The FREE Builder is extended without touching the frozen core:

- **Components** — register section/block definitions in PHP (`src/Definitions/…`); they surface in
  the palette and Navigator automatically.
- **Patterns** — `PatternProvider` (default `LocalPatternProvider`); a persistent backend swaps in at
  the provider seam without changing pickers or runtime.
- **Templates** — `TemplateProvider` + `TemplateDefinitionSource` (builtin/user/imported) resolved by
  `TemplateSourceResolver`; template packs register via `BuiltinTemplatePackRegistry` /
  `TemplateDistributionRegistry`.
- **Insert categories** — one `registry.register(makeInsertProvider(key, open))` call; the store,
  panel, and pipelines are untouched. Reserved keys (`marketplace`, `remote`, `cloud`) are declared
  but inert.
- **Field editors** — `registerFieldEditor(type, descriptor)` extends the Inspector.
- **Asset sources** — the `mediaPicker` seam accepts an alternate picker implementation.

## 4. User workflow

1. **Insert** — `+ Insert` (toolbar) or **Ctrl/Cmd+I** opens the unified launcher → choose Blocks,
   Patterns, Templates, or Assets → the existing experience opens.
2. **Build** — drag from the palette or Add; select on canvas/Navigator; edit in the Inspector.
3. **Reuse** — save a selection as a Pattern; apply a Template (confirm → one undo).
4. **Manage** — rename/duplicate/delete patterns & templates; import/export template packages.
5. **Edit safely** — Undo/Redo, copy/cut/paste/duplicate/delete; every change is one undo step.
6. **Publish** — Save draft → Publish; preview reflects the current draft.

## 5. Developer workflow

```bash
cd plugins/page-builder
npm test            # vitest — full unit/integration/UI suite (161 files, 1351 tests)
npm run build       # editor + frontend-shell + frontend-iframe + workspace bundles
npm run dev         # vite build --watch (editor)
```

- Tests use `happy-dom`; components mount with Pinia. New behavior must ship with a test.
- Keep discovery layers pure — a picker/library/router must never call `executeCommand`.
- Registries register **once at boot** (`main.ts`) and are idempotent.

## 6. Limitations & known constraints (FREE v1)

- **Single command pipeline** — all mutation is server-driven; no offline/optimistic local mutation.
- **In-memory pattern/template providers** — the FREE build persists templates/patterns via the
  provider seam only where a backend is wired; the default local providers are session-scoped.
- **No marketplace / remote / cloud** — those source keys are reserved and inert by design.
- **No AI, dynamic content, live collaboration, or premium components** — out of scope for FREE v1.
- **Canvas context menu** is mouse-oriented (dismisses via global Escape; items are tabbable) — full
  arrow-key roving is a documented future a11y enhancement, not a defect.
- **Backdrop overlays** are decorative; keyboard users dismiss dialogs via Escape / Cancel.

## 7. Release Candidate checklist

| Gate | Result | Evidence |
|------|--------|----------|
| **Architecture** | ✅ PASS | Module boundaries hold (patterns↛templates; insert router-only; discovery layers dispatch no commands). No TODO/FIXME/debugger; all `console.*` are `import.meta.env.DEV`-guarded diagnostics. [Hardening report](./audits/phase-26.39-rc-hardening-report.md) |
| **Performance** | ✅ PASS | Every global listener paired with teardown; no deep watchers/duplicate watchers; registries idempotent at boot; no hot-path deep clones. [Performance report](./audits/phase-26.39-performance-report.md) |
| **Accessibility** | ✅ PASS | 19 dialogs/menus inventoried; 4 missing-Escape + 1 missing-label defects fixed & tested; remainder compliant. [Accessibility report](./audits/phase-26.39-accessibility-report.md) |
| **UX / Empty states** | ✅ PASS | Empty states present across all panels; pattern-management feedback gap fixed. "Applying…" label confirmed already present (false positive rejected). |
| **Error handling** | ✅ PASS | Import/apply/preview/asset/provider paths are validation-first, atomic, fault-isolated; boot registration guarded. |
| **Regression** | ✅ PASS | No runtime/CommandBus/BuilderState/serialization source changed. [Regression report](./audits/phase-26.39-regression-report.md) |
| **Tests** | ✅ PASS | 161 files / 1351 passing; +4 RC regression tests. |
| **Documentation** | ✅ PASS | This RC doc + four audit reports under `docs/audits/`. |

**Verdict: Page Builder FREE v1 qualifies as a Release Candidate.** Proceed to the review gate
(Architecture / UX / Accessibility / Regression / Performance) before roadmap phase 26.40.
