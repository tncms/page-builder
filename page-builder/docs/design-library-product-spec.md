# Design Library — Product Specification

_Phase 26.47C — the frozen product model for applying Page Templates and
registering reusable Builder Resources._

This is the master product specification. It is **documentation only** — it
defines and freezes the two workflows that every later phase (26.47D, 26.47E,
26.48+) must implement. No runtime, preview, repository, serialization,
BuilderState, CommandBus, or Extension-API behaviour changes in this phase.

Companion specs:

- [page-template-application.md](page-template-application.md) — Part A workflow.
- [builder-resource-registration.md](builder-resource-registration.md) — Part B workflow.
- [builder-resource-lifecycle.md](builder-resource-lifecycle.md) — resource lifecycle.

Normative keywords (MUST / MUST NOT / SHOULD / MAY) follow RFC 2119, matching the
existing Design specs (26.40B).

---

## 1. The core product principle

The Design Library exposes **two fundamentally different resource categories**.
They MUST NOT share the same user workflow, the same verb, or the same surface.

```
                        Design Library
                              │
             ┌────────────────┴────────────────┐
             │                                  │
        Workflow A                         Workflow B
      Page Templates                   Builder Resources
             │                                  │
   Apply to a whole Page/Post        Become reusable Builder elements
             │                                  │
     verb: "Use Template"          verbs: "Insert" / "Drag" / "Use in Builder"
```

| | **Page Template** | **Builder Resource** |
| --- | --- | --- |
| Kind | `page` | `section`, `block`, `kit` (reserved) |
| Represents | A complete page layout | A reusable Builder element |
| Applied to | A Page or Post document | Anywhere inside a Builder layout |
| Verb | **Use Template** | **Insert / Drag / Use in Builder** |
| Appears in Block/Section Palette | **Never** | **Yes** (once registered) |
| Opens a picker every time | Yes (deliberate, document-scoped) | No (registered once, always present) |
| Engine | `FullPageImportService` (replace draft) | Existing Builder insertion pipeline |

The single most important rule of this phase: **never confuse a Page Template
with a Builder Resource.**

---

## 2. Why they are different

A Page Template is a *destination decision*: "make this whole page look like
that." It is destructive at the draft level (it replaces the current design), so
it is always a deliberate, confirmed, document-scoped act.

A Builder Resource is a *building block*: "give me this section/block to place." It
is additive, undoable, and behaves exactly like a native element. Forcing it
through an "apply / import" confirmation each time would be hostile; forcing a
whole-page template into the Block Palette would be dangerous.

Keeping the verbs distinct keeps the mental model distinct.

---

## 3. Authoritative foundations (reused unchanged)

Every foundation below is reused **without modification**. There is no second
repository, preview engine, runtime, or import pipeline.

| Foundation | Phase | Role in this spec |
| --- | --- | --- |
| Design Library Architecture | 26.40A | Product architecture (facade + `DesignResource{kind}`) |
| Design Resource Specification | 26.40B | RID, field categories, package v2 |
| Design Resource Lifecycle | 26.40C | State machine, publication, validation |
| Runtime Preview | 26.41 | The only preview engine |
| `DesignResourceRepository` | 26.42 | The **authoritative** source of resources |
| Design Library UI | 26.43 | Admin browser surface |
| Full Page Import | 26.44 | `FullPageImportService` — the only page-apply engine |
| FREE / PRO Boundary | 26.45 | Availability gating |
| Starter Resources | 26.46 | Built-in catalog |
| Package Sharing | 26.47 | `.tncms-design` import/export |
| Admin Migration | 26.47A | Design Library admin nav |
| Builder Resource Picker | 26.47B | Context-aware picker seam |

**Repository authority (invariant):** the `DesignResourceRepository` remains the
single source of truth for what resources exist. Neither workflow may invent,
duplicate, or shadow resources outside the repository.

---

## 4. Terminology (frozen)

The verb is chosen by *category*, never by convenience. See
[§ Terminology specification](#8-terminology-specification) for the full table
and the banned combinations.

| Object | Correct verb | Never say |
| --- | --- | --- |
| `.tncms-design` package | **Import Package** | — |
| Page Template (`page`) | **Use Template** | Import / Insert / Add |
| Section / Block / Kit | **Insert** · **Drag** · **Use in Builder** | Apply / Import |

> **Migration note.** The current code labels the full-page picker action
> `Import Design` (`PickerContext::actionLabel()` and the 26.47A page card).
> This spec **retires that label** for Page Templates. Phase 26.47D MUST change
> the page action to **Use Template** / **Use This Template**. "Import" survives
> only for `.tncms-design` *packages*.

---

## 5. Responsibility matrix

| Concern | Owner | Notes |
| --- | --- | --- |
| What resources exist | `DesignResourceRepository` | Authoritative; both workflows read it |
| Resource preview | Runtime Preview (26.41) | Only preview engine |
| Availability (FREE/PRO) | `ResourceAvailabilityResolver` (26.45) | Gates *actions*, not visibility |
| Apply a Page Template | `FullPageImportService` (26.44) | Draft-only, snapshot, transactional |
| Insert a Builder Resource | Existing Builder insertion pipeline | Clones node/runtime ids + bindings |
| Which kind a context may offer | `PickerContext` (26.47B) | Single-kind per context |
| Register Builder Resources into palettes | **Builder Registry** (26.47E, future) | Reads repository; no duplication |
| Terminology / labels | This spec | Frozen here |
| Mutation of Builder state | The **Builder** only | Library/picker never mutate |

---

## 6. Workflow A — Page Template application (summary)

Full detail in [page-template-application.md](page-template-application.md).

- Page Templates are **applied**, never inserted. They never appear in any
  palette or drag library.
- A Page Template always targets a Page or Post — existing or newly created.
- Applying reuses `FullPageImportService`: **draft only**, a recovery snapshot is
  created, published content is untouched until publish.
- Two entry points:
  - **From the Builder** (editing a Page/Post): toolbar **Use Templates** →
    repository filtered `kind=page` → replace-confirmation → apply → stay in Builder.
  - **From the Design Library**: card **Use This Template** → "where would you like
    to use this?" → Create New (Page/Post) or Use Existing (Page/Post) → apply →
    redirect to Builder.

---

## 7. Workflow B — Builder Resource registration (summary)

Full detail in [builder-resource-registration.md](builder-resource-registration.md).

- Sections, Blocks, and Kits are Builder Resources. Once available they appear
  **naturally inside the Builder** — they do not open a picker every time.

```
Package → Repository → Resource Registration → Builder Registry → Builder Palette
```

- The Builder Registry reads the `DesignResourceRepository` and registers every
  compatible resource. **No duplication.**
- The palette groups resources by source (**Built-in**, **Imported Designs**,
  **My Designs**, **Future Marketplace**) — groups only, never duplicated items.
- Only compatible kinds appear in each palette (Section→Section palette,
  Block→Block palette, Kit→Kit palette). Kinds never mix.
- Registered resources behave exactly like native elements: click-insert, drag,
  duplicate, delete, undo, redo, copy, paste — through the **existing** insertion
  pipeline. No second insertion engine and no special runtime.

---

## 8. Terminology specification

| Context | Object | Frozen label | Forbidden here |
| --- | --- | --- | --- |
| Package management | `.tncms-design` file | Import Package | Use / Insert |
| Page editor toolbar | Page Template set | Use Templates | Import / Insert |
| Design Library card (page) | Page Template | Use This Template | Import Design / Insert |
| Replace confirmation | Page Template | Use Template | Import / Apply* |
| Section palette | Section resource | Insert Section · Drag | Import / Apply |
| Block palette | Block resource | Insert Block · Drag | Import / Apply |
| Kit palette (reserved) | Kit resource | Use in Builder | Import / Apply |

\* "Apply" is acceptable prose when describing the *mechanism* ("the template is
applied to the draft"), but the user-facing button MUST read **Use Template**.

Absolute rule: **"Import" MUST NOT be used for Page Templates.** Packages are
imported; pages use templates; builder resources are inserted.

---

## 9. Future user resources (reserved, not implemented)

Reserve — do not build in this phase or the next.

```
Builder → Right Click → Save as Design → My Designs → Builder Palette
```

- A user selection in the Builder can be saved as a Design Resource.
- It lands under **My Designs** and appears in the appropriate palette group.
- Delivered later as Phase 26.48 (Save as Design). Only the interface and the
  `My Designs` palette group are reserved here.

---

## 10. Implementation roadmap

This phase produces **specification only**. The phases below define
responsibilities and interfaces; none are implemented here.

| Phase | Scope | Status |
| --- | --- | --- |
| **26.47C** | This specification | Freeze workflows + terminology |
| 26.47D | Page Template Application Workflow | **Implemented** (Workflow A; see [page-template-application-workflow.md](page-template-application-workflow.md)) |
| 26.47E | Builder Resource Registration | Implements Workflow B |
| 26.48 | Save as Design | User-authored resources |
| 26.49 | Design Resource Management | Manage/organize resources |
| 26.50 | Community Library | Community distribution |
| 26.51 | Marketplace | Paid distribution |
| 26.52 | PRO Library | PRO-tier catalog |
| _later_ | **Portable Design Package & Media Resolution Architecture** | Reserved — portable-media export/import for templates (deferred from 26.47D; the `.tncms-design` format + Media Manager stay frozen until then) |

---

## 11. Acceptance criteria (this phase)

- [x] Page Templates formally defined as complete layouts.
- [x] Builder Resources formally defined as reusable Builder elements.
- [x] Page Template workflow frozen (Part A).
- [x] Builder Resource workflow frozen (Part B).
- [x] Terminology frozen.
- [x] Future roadmap documented.
- [x] Repository remains authoritative.
- [x] Runtime, Preview, Builder, `FullPageImportService`, Package Sharing,
      serialization, BuilderState, CommandBus, and Extension API **unchanged**.
