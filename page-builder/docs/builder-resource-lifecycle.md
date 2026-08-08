# Builder Resource Lifecycle

_Phase 26.47C — the lifecycle of a Builder Resource, from origin to placed
element, and the reserved path for user-authored resources._

Documentation only. This is the resource-centric companion to
[builder-resource-registration.md](builder-resource-registration.md) (the
workflow) and [design-library-product-spec.md](design-library-product-spec.md)
(the product model). It builds on the general Design lifecycle (26.40C) — it does
not replace it; it narrows it to Builder Resources (`section`, `block`, `kit`).

Nothing here changes runtime, preview, the repository, serialization,
BuilderState, the CommandBus, or the Extension API.

---

## 1. Scope

This lifecycle covers resources whose `kind` is a **palette kind**:

```
section   ·   block   ·   kit (reserved)
```

Page Templates (`kind=page`) follow a different lifecycle — they are *applied*,
not *registered* — documented in
[page-template-application.md](page-template-application.md).

---

## 2. Origins

A Builder Resource can enter the repository from three origins. All three land in
the **same** `DesignResourceRepository` and are treated identically thereafter.

| Origin | Source group | Provider | Phase |
| --- | --- | --- | --- |
| Built-in / starter | **Built-in** | `LocalResourceProvider` / `StarterResourceProvider` | 26.42 / 26.46 |
| Imported package | **Imported Designs** | `PackageResourceProvider` / `ImportedResourceProvider` | 26.42 / 26.47 |
| User "Save as Design" | **My Designs** | _reserved provider_ | 26.48 (reserved) |

The origin determines only the palette **group** (Rule B4). It does not create a
second registry, a second preview path, or a second insertion engine.

---

## 3. Lifecycle stages

```
   ┌─────────┐   publish / import / save   ┌──────────────┐
   │ authored│ ──────────────────────────► │ in repository │
   └─────────┘                             └──────┬───────┘
                                                  │ Builder boot
                                                  ▼
                                          ┌──────────────┐
                                          │  registered  │
                                          └──────┬───────┘
                              available │        │ locked (FREE/PRO, 26.45)
                                        ▼        ▼
                               ┌──────────────┐  ┌──────────────┐
                               │ insertable   │  │ preview-only │
                               └──────┬───────┘  └──────────────┘
                                      │ insert (clone ids + bindings)
                                      ▼
                               ┌──────────────┐
                               │ placed node  │ ◄── ordinary Builder element
                               └──────────────┘
```

1. **Authored** — created by a plugin author, packaged, or (future) saved by a
   user from a Builder selection.
2. **In repository** — the resource exists in `DesignResourceRepository`, keyed by
   its RID (`<source>:<kind>:<resourceId>`).
3. **Registered** — the Builder Registry has read it into the palette
   (idempotently). Registration is a projection of the repository, never a copy.
4. **Insertable / preview-only** — availability (26.45) decides whether the user
   may insert or only preview. Locking never removes the resource from the list.
5. **Placed node** — inserted through the existing pipeline (clone node ids,
   runtime ids, bindings). From here it is an ordinary element: undo, redo, copy,
   paste, duplicate, delete all apply.

---

## 4. Invariants across the lifecycle

- **Kind-independent handling.** Section, block, and kit share one lifecycle;
  only the target palette differs (Rule B5).
- **Repository authority.** A resource is registered iff it is in the repository.
  Removing it from the repository removes it from the palette on next boot.
- **Idempotent registration.** Re-registering the same RID is a no-op — no
  duplicates (Rule B3).
- **Copy-on-insert.** Insertion never links to the source resource; it clones.
  Editing a placed node never mutates the resource, and vice-versa.
- **Runtime-frozen.** Registration and insertion add no new runtime. A placed
  Builder Resource renders through the same runtime as any native element.
- **License gates actors, not states.** FREE/PRO changes whether a user may
  *insert*, not whether the resource *exists* or *previews* (mirrors 26.40C).

---

## 5. Reserved — user-authored resources (Save as Design)

Reserved for Phase 26.48. Do not implement.

```
Builder → Right Click → Save as Design → My Designs → Builder Palette
```

- A Builder selection (a section, a block, or a group) is captured as a new
  `DesignResource`.
- It is written to the repository via the reserved user provider, source group
  **My Designs**.
- From "in repository" onward it follows the identical lifecycle above —
  registration, availability, insertion — with no special-casing.

Only the interface and the **My Designs** group are reserved here. No capture,
serialization, or persistence is built in this phase.

---

## 6. Responsibility matrix

| Stage | Owner |
| --- | --- |
| Author / package / save | Plugin author · Package Sharing (26.47) · Save-as-Design (26.48, reserved) |
| Hold + key by RID | `DesignResourceRepository` (26.42) |
| Register into palette | Builder Registry (26.47E) |
| Availability (insertable vs preview-only) | `ResourceAvailabilityResolver` (26.45) |
| Preview | Runtime Preview (26.41) |
| Insert (clone ids + bindings) | Existing insertion pipeline |
| Post-insert editing | Existing Builder (unchanged) |

---

## 7. Out of scope for this phase

Specification only. Registration is implemented in 26.47E; user-authored capture
in 26.48; management/organization in 26.49. This phase changes no code.
