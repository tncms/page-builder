# Working Copy Contract (Phase 26.49A, Rule 6)

> **Status: FROZEN.** Implementation seam:
> `Management\DesignResourceManager::openInBuilder()` (Phase 26.49).

## Principle

**The Builder never edits Repository Resources.** To change a design's content:

```
Repository resource
  ↓  Open in Builder
Working Copy            — a deep, detached copy of the saved payload
  ↓  edit in the Builder
Builder draft
  ↓  Save as Design
Capture Pipeline
  ↓
New Resource
```

The repository payload **never mutates** as part of this flow.

## Guarantees

- `openInBuilder()` returns a **deep copy** of the saved payload. The repository is
  only ever read.
- Builder drafts and Repository resources are **separate lifecycles**. Mutating the
  working copy cannot affect the stored resource.
- Finishing an edit flows through **Save as Design** — a new
  [Capture](capture-pipeline-contract.md) — never an in-place update. *Update
  Existing* is reserved.

## Forbidden

- `Repository → Builder → Repository` (writing an edited resource back over itself).
- Any direct payload mutation from the Builder or the management surface.

This isolation is the reason a captured resource can be treated as immutable and
content-addressed, which Community, Marketplace and Portable Package depend on.
