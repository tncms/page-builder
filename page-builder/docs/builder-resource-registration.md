# Builder Resource Registration

_Phase 26.47C — Part B. The frozen workflow for turning Sections, Blocks, and
Kits into first-class Builder elements._

Documentation only. This spec governs Phase 26.47E (the implementation). Nothing
in this phase changes the Builder, the insertion pipeline, serialization,
BuilderState, the CommandBus, or the Extension API.

See [design-library-product-spec.md](design-library-product-spec.md) for the
two-workflow model and [builder-resource-lifecycle.md](builder-resource-lifecycle.md)
for the resource lifecycle.

---

## 1. Definition

A **Builder Resource** is a reusable Builder element — a `DesignResource` with
`kind` of `section`, `block`, or `kit` (kit reserved). Unlike a Page Template, a
Builder Resource is *not* a whole page; it is a piece you place inside a layout.

Consequences (all normative):

- **B1.** Sections, Blocks, and Kits are Builder Resources, **not** Page
  Templates.
- **B2.** Once available, a Builder Resource appears **naturally inside the
  Builder**. It MUST NOT open a picker every time it is used. Registration makes
  it a permanent palette element.

---

## 2. Architecture

```
   Package            (built-in, imported .tncms-design, or future user save)
      │
      ▼
   DesignResourceRepository        ◄── authoritative source of resources
      │
      ▼
   Resource Registration           ◄── reads the repository, one time per boot
      │
      ▼
   Builder Registry                ◄── the Builder's element registry
      │
      ▼
   Builder Palette                 ◄── grouped, deduplicated, kind-correct
```

Every stage reuses existing infrastructure. There is no second repository, no
second registry semantics, no duplication of resources.

---

## 3. Registration (Rule B3)

- **B3.** The Builder Registry **reads** the `DesignResourceRepository`. Every
  *compatible* Builder Resource is registered exactly once.
- Registration is a read: it does not copy, transform, or persist resources
  outside the repository.
- The repository stays authoritative. If a resource is not in the repository, it
  is not in the palette.
- Registration MUST be **idempotent** — re-running it (e.g. on Builder re-boot)
  MUST NOT create duplicates. This mirrors the existing idempotent insert-provider
  wiring (`registerBuilderInsertProviders`, Phase 26.38).

```
for each resource R in repository.query():
    if R.kind is a palette kind and R is available:
        registry.register(R)          # dedup by RID; re-register is a no-op
```

---

## 4. Grouping (Rule B4)

- **B4.** The Builder Palette groups registered resources by **source**. Groups
  only — never duplicated resources.

```
  Builder Palette (per palette kind)
  ├── Built-in
  ├── Imported Designs
  ├── My Designs            (reserved for 26.48 Save as Design)
  └── Future Marketplace    (reserved)
```

The source is the resource's provider origin (Local/built-in, Package/imported,
user, marketplace). A resource belongs to exactly one group; it is never shown
twice.

---

## 5. Compatibility (Rule B5)

- **B5.** Only compatible resources appear, and kinds **never mix**.

| Resource kind | Palette |
| --- | --- |
| `section` | Section Palette |
| `block` | Block Palette |
| `kit` (reserved) | Kit Palette |

A Section MUST NOT appear in the Block Palette and vice-versa. Page Templates
(`kind=page`) are never registered here — they belong to Workflow A.

---

## 6. Insertion (Rules B6, B7)

- **B6.** A registered Builder Resource behaves **exactly like a native element**.
  It MUST support: click-insert, drag, duplicate, delete, undo, redo, copy, paste.
  There is **no special runtime** for registered resources.
- **B7.** Insertion reuses the **existing insertion pipeline**. Insertion clones:
  - node ids
  - runtime ids
  - bindings

  There is **no second insertion engine**. This is the same clone-on-insert
  behaviour the Builder already uses for native palette items (mirroring
  `NodeIdGenerator` / the full-page import's fresh-id pass).

```
User picks a registered resource in the palette
        │
        ▼
Existing insertion pipeline
        │  clone node ids · clone runtime ids · rebind
        ▼
Resource placed into the layout as an ordinary element
        │
        ▼
Undo / redo / copy / paste all work natively
```

---

## 7. Flow diagram — end to end

```
  Author publishes a Section/Block         (or imports a .tncms-design package)
        │
        ▼
  DesignResourceRepository holds the resource
        │
        ▼  (Builder boot)
  Resource Registration reads repository → Builder Registry
        │
        ▼
  Builder Palette shows it under its source group, in its kind's palette
        │
        ▼
  User inserts / drags it → existing insertion pipeline → native element
```

---

## 8. State diagram — a Builder Resource in the palette

```
   ┌──────────────┐   published/imported    ┌──────────────┐
   │  in package  │ ──────────────────────► │ in repository │
   └──────────────┘                         └──────┬───────┘
                                                   │ Builder boot: register
                                                   ▼
                                            ┌──────────────┐
                                            │  registered  │  ◄── in palette
                                            └──────┬───────┘
                                                   │ insert (clone ids)
                                                   ▼
                                            ┌──────────────┐
                                            │  placed node │  ◄── native element
                                            └──────────────┘

   Availability (FREE/PRO) may mark a registered resource "locked": it stays
   listed and previewable, but insertion is gated (26.45).
```

---

## 9. Responsibility matrix (Part B)

| Concern | Owner |
| --- | --- |
| Hold resources | `DesignResourceRepository` (authoritative) |
| Which resources are compatible | Builder Registry (reads repository) |
| Register into palettes (idempotent) | Builder Registry (26.47E) |
| Group by source | Builder Palette |
| Availability lock | `ResourceAvailabilityResolver` (26.45) |
| Insert / clone ids / rebind | Existing insertion pipeline |
| Undo / redo / copy / paste | Existing Builder (unchanged) |

---

## 10. Invariants

- The repository is the single source of truth; registration never duplicates it.
- Registration is idempotent — re-boot never creates duplicates.
- Kinds never mix across palettes.
- Registered resources are ordinary elements — no special runtime, no second
  insertion engine.
- Grouping is by source; a resource appears in exactly one group.

---

## 11. Out of scope for this phase

This document specifies the workflow. Phase 26.47E implements the Builder Registry
that reads the repository and populates the palette groups, on top of the
unchanged insertion pipeline. **My Designs** is a reserved group filled by Phase
26.48 (Save as Design); **Future Marketplace** is reserved for 26.51.
