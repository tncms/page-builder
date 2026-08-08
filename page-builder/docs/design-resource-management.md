# Design Resource Management — "My Designs" (Phase 26.49)

This document specifies the management lifecycle for **user-created Design
Resources** ("My Designs") and the **immutable resource contract** that
Community, Marketplace and Portable Package will depend on.

> **Scope.** This phase manages Design Resources. It does **not** edit Builder
> content, it does **not** replace the Design Capture Pipeline, and it does **not**
> modify normalized Builder payloads. The Design Resource Repository remains the
> single source of truth.

---

## 1. Core principle

```
Builder creates designs.        (Design Capture Pipeline, 26.48)
Repository owns designs.         (Design Resource Repository, 26.42)
Management edits metadata only.  (this phase)
Builder edits design content.    (a new capture, never an in-place edit)
```

The Design Manager **never** edits a normalized payload. It only edits
descriptive metadata and flips lifecycle flags (archived / deleted).

---

## 2. Where "My Designs" lives

"My Designs" is a surface **inside** the Design Library — never a second library.
The Design Library now contains three real sources plus reserved future ones:

| Source        | Origin                              | Status     |
|---------------|-------------------------------------|------------|
| Built-in      | `LocalResourceProvider` / starters  | shipped    |
| Imported      | `.tncms-design` packages (26.47)    | shipped    |
| **My Designs**| captured from the Builder (26.48)   | **this phase** |
| Marketplace / Community / PRO | —                   | reserved   |

The management surface is served at `page-builder/library/my-designs` and linked
from the Design Library discovery tabs.

---

## 3. Immutable resource contract (hard contract)

Once captured, a Design Resource is **immutable**:

| Field           | Mutable? | Owner of change                       |
|-----------------|----------|----------------------------------------|
| RID             | ❌ never | system (assigned at capture)           |
| Fingerprint     | ❌ never | system (`DesignFingerprint`, capture)  |
| Normalized payload | ❌ never | Design Capture Pipeline only        |
| Compatibility   | ❌ never | system                                 |
| Preview reference | ❌ never | Runtime Preview route                |
| Created At / Author | ❌ never | capture                            |
| Name, Description, Category, Tags, Visibility, Version Notes | ✅ | Metadata Updater |
| Archived / Deleted flags | ✅ | Archive Manager (lifecycle only)   |

**Any content modification produces a NEW Design Resource through the Design
Capture Pipeline.** There is no in-place payload edit and no "Update Existing"
(reserved). Community, Marketplace and Portable Package rely on this: a RID +
fingerprint is a stable, content-addressed handle that never silently changes
underneath a consumer.

---

## 4. Metadata management

Editable (and only these): **Name, Description, Category, Tags, Visibility,
Version Notes**. Everything else is system-managed and is never exposed for
editing — the Admin surface never shows raw JSON, the payload, the RID, the
fingerprint or compatibility (Rule 20).

- `MetadataUpdater::apply()` is pure: it returns a new record, validates the
  editable fields, bumps `updatedAt`, and copies every immutable field through
  untouched. **The fingerprint is never recomputed** (Rule 10).
- Visibility is limited to `private` / `unlisted`. Sharing and publishing are
  reserved (Rule 21).

---

## 5. Lifecycle

| Action  | Effect                                                             | Recoverable |
|---------|--------------------------------------------------------------------|-------------|
| Archive | Hidden from normal browsing (repository, palette, library) and preview | ✅ Restore |
| Restore | Exact inverse of Archive — nothing else                            | —           |
| Delete  | **Soft only** — flags `deleted`, never removes the file            | ✅ (until a future purge job) |
| Duplicate | New RID + resourceId, **same payload + same fingerprint**, reset version, name `… (Copy)` | — |

Archived and deleted resources are filtered out of the `UserDesignProvider`, so
they leave normal browsing and the Builder palette. The management surface reads
the store directly, so archived designs remain visible under its **Archived**
filter and can be restored or deleted.

There is **no hard delete**. A future cleanup job may purge archived/deleted
records; it is not implemented.

---

## 6. Working-copy architecture — "Open in Builder"

To change a design's content the user never edits it in the Admin. Instead:

```
Open in Builder → edit an isolated working copy → Save as Design → new Capture
```

`DesignResourceManager::openInBuilder()` returns a **deep, detached copy** of the
saved payload. The repository payload is only ever read, so it stays
byte-identical regardless of what the Builder does to the working copy. Builder
drafts and Repository resources are separate lifecycles (Rule 23). Finishing an
edit flows through **Save as Design** — a new Design Capture — never an update of
the original resource. "Update Existing" is reserved.

---

## 7. Authorization

Every management action is verified **server-side** (`OwnershipResolver`). Only
the **owner** (matched against the record's stored author) or an **authorized
administrator** (holding `page-builder.publish`) may edit metadata, archive,
restore, delete, duplicate, use or open a design. Client-claimed ownership is
never trusted. The coarse controller gate is `page-builder.edit`; ownership is
enforced inside the manager and surfaced as a `403`.

---

## 8. Registry refresh strategy

| Trigger                       | Repository refresh | Registry refresh |
|-------------------------------|--------------------|------------------|
| Resource created (Capture)    | ✅                 | ✅               |
| Archive / Restore / Delete    | ✅                 | ✅               |
| Duplicate                     | ✅                 | ✅               |
| **Metadata edit**             | ✅                 | ❌ (never forced)|

Metadata edits must not rebuild the Builder runtime (Rule 12): the manager never
calls `BuilderResourceRegistry::refresh()` for a metadata change.

---

## 9. Events (no listeners)

The manager dispatches, but ships **no** listeners (Rule 18):

`DesignMetadataUpdated`, `DesignArchived`, `DesignRestored`, `DesignDeleted`,
`DesignDuplicated`.

---

## 10. Versioning

The current version is **read-only** metadata. History, diff, rollback and a
version tree are **not** implemented — only version metadata (and editable version
notes) are displayed (Rule 17).

---

## 11. Reserved (not implemented)

Favorite · Share · Publish · Marketplace · Community · Export · Portable Package ·
Update Existing. These are reserved seams only.
