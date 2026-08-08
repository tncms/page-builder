# Page Template Application

_Phase 26.47C — Part A. The frozen workflow for applying a Page Template to a
Page or Post._

Documentation only. This spec governs Phase 26.47D (the implementation). Nothing
in this phase changes runtime, preview, `FullPageImportService`, serialization,
BuilderState, or the CommandBus.

See [design-library-product-spec.md](design-library-product-spec.md) for the
two-workflow model and frozen terminology.

---

## 1. Definition

A **Page Template** is a complete page layout — a `DesignResource` with
`kind = page`. It represents the whole document design, not a fragment.

Consequences (all normative):

- **A1.** A Page Template is **not** a Builder Element. It MUST NOT appear in the
  Block Palette, the Section Palette, or any drag library.
- **A2.** A Page Template is **applied**, never inserted. The user-facing verb is
  **Use Template**. The words *Insert*, *Import*, and *Add* MUST NOT be used for
  Page Templates.
- **A3.** A Page Template always targets a Page or Post — an existing one or a
  newly created one. It MUST NOT be applied without a document context.

Valid targets, and nothing else:

```
existing Page   ·   existing Post   ·   new Page   ·   new Post
```

---

## 2. The application engine

Every Page Template application — from either entry point — MUST reuse
**`FullPageImportService`** (Phase 26.44). No second apply path exists.

Guarantees inherited from 26.44 (unchanged):

- **Draft only.** The application writes the Builder draft via `saveDraft`.
  Published content is untouched until the user publishes.
- **Recovery snapshot.** A snapshot of the current draft is taken before
  replacement, so the previous design is recoverable.
- **Transactional.** `plan()` is a read-only dry run; `import()` is atomic
  (verify → snapshot → fresh ids → save once → verify → rollback on failure).
- **Repository-resolved.** The template RID is resolved through the repository;
  the client payload is never trusted.
- **Blockers** are surfaced (wrong kind, unknown components, unsafe asset, stale
  revision, plan mismatch) before any write.

This spec adds **no** new engine behaviour. It only defines *where the two entry
points sit* and *what the user sees*.

---

## 3. Entry point 1 — from the Builder (editing a Page/Post)

When the user is editing a Page or Post, the Builder toolbar offers:

```
  ┌───────────────┐
  │ Use Templates │
  └───────────────┘
```

Flow:

```
Builder toolbar "Use Templates"
        │
        ▼
Open "Page Templates"            (Design Library, Picker mode — 26.47B)
Repository filtered: kind = page
        │
        ▼
User selects a template
        │
        ▼
Replace-current-design confirmation   (see § 5)
        │
        ├── Cancel ─────────────► back to Builder, draft untouched
        │
        └── Use Template
                │
                ▼
        FullPageImportService.import()   (draft only, snapshot created)
                │
                ▼
        Builder re-reads the fresh draft — user stays in the editor
```

The picker is the Picker-mode Design Library from 26.47B, scoped to `kind=page`
with the owner document. **The Builder owns the mutation** — the library returns
the chosen template; the Builder drives `FullPageImportService`.

---

## 4. Entry point 2 — from the Design Library

Each Page Template card in the Design Library shows exactly:

```
  ┌──────────────────────┐
  │       Preview        │
  ├──────────────────────┤
  │  Use This Template   │   ◄── primary action (never "Insert")
  │       Export         │
  └──────────────────────┘
```

There is **no Insert** action on a Page Template card.

Clicking **Use This Template** asks for a destination first, because a template
has no meaning without a document:

```
        Where would you like to use this template?

   ┌── Create New ──────────┐     ┌── Use Existing ────────┐
   │   ○ Page               │     │   ○ Page               │
   │   ○ Post               │     │   ○ Post               │
   └────────────────────────┘     └────────────────────────┘
```

### 4a. Create New

```
Template
   │
   ▼
Create a Draft Page / Post          (new document, status = Draft)
   │
   ▼
Apply Template                      (FullPageImportService → draft)
   │
   ▼
Redirect into the Builder
```

Published state of the new document: **Draft**. The template fills the draft; the
user reviews and publishes when ready.

### 4b. Use Existing

```
Template
   │
   ▼
Search / pick a Page or Post        (choose the target document)
   │
   ▼
Replace-current-design confirmation (§ 5)
   │
   ▼
Recovery Snapshot                   (of the target's current draft)
   │
   ▼
Apply Template                      (FullPageImportService → draft)
   │
   ▼
Redirect into the Builder
```

---

## 5. The replace confirmation

Because applying a template replaces the current draft design, both Builder and
"Use Existing" paths MUST show a confirmation before any write:

```
  Replace Current Design?

  Current draft
    Sections: X
    Blocks:   Y

  Selected Template
    Sections: X
    Blocks:   Y

  Published content remains unchanged until Publish.
  A recovery snapshot will be created.

  [ Cancel ]        [ Use Template ]
```

- The counts come from the read-only `FullPageImportService.plan()` — no state is
  mutated to render the dialog.
- The button reads **Use Template**, never *Import* or *Apply*.
- **Create New** MAY skip the "replace" framing (the draft is empty) but still
  routes through `FullPageImportService`.

---

## 6. State diagram — target document

```
                    ┌─────────────┐
   Create New ─────►│  new Draft  │
                    └──────┬──────┘
                           │ apply template
                           ▼
   Use Existing     ┌──────────────┐   apply template   ┌──────────────┐
   (has draft) ────►│ Draft (old)  │──── snapshot ─────►│ Draft (new)  │
                    └──────────────┘                    └──────┬───────┘
                                                               │ user Publish
                                                               ▼
                                                        ┌──────────────┐
                                                        │  Published   │
                                                        └──────────────┘

   Published content is never touched by "apply"; only the draft changes.
   The pre-apply draft is always recoverable from the snapshot.
```

---

## 7. Responsibility matrix (Part A)

| Step | Owner |
| --- | --- |
| Filter templates (`kind=page`) | `DesignResourceRepository` + `ResourceQuery` |
| Preview a template | Runtime Preview (26.41) |
| Availability gate | `ResourceAvailabilityResolver` (26.45) |
| Compute replace counts | `FullPageImportService.plan()` (read-only) |
| Create the new draft document | Host CMS document layer (Page/Post) |
| Apply to draft + snapshot | `FullPageImportService.import()` |
| Redirect into Builder | Host controller |
| Own the mutation | The Builder / import service — never the library UI |

---

## 8. Invariants

- Page Templates MUST NOT enter any palette or drag surface (A1).
- The only apply engine is `FullPageImportService` — draft-only, snapshotted,
  transactional.
- Published content is never modified by application.
- The verb is **Use Template**; "Import" is reserved for `.tncms-design` packages.
- The repository stays authoritative; the client payload is never trusted.

---

## 9. Out of scope for this phase

This document specifies the workflow. It does **not** implement it. Phase 26.47D
builds the toolbar entry, the destination chooser, the confirmation dialog, and
the create-draft wiring on top of the unchanged `FullPageImportService`.
