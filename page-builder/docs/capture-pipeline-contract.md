# Capture Pipeline Contract (Phase 26.49A, Rule 8)

> **Status: FROZEN.** Implementation: `Capture\DesignCapturePipeline`. This is the
> **ONLY** content-persistence pipeline on the platform.

## Responsibility

The Capture Pipeline is the single path by which content enters the Repository:

```
Builder selection
  → capture
  → normalize      (Capture\DesignCaptureNormalizer — id-free, deterministic)
  → validate       (Capture\DesignCaptureValidator)
  → fingerprint    (Capture\DesignFingerprint — canonical, payload-only)
  → preview        (Capture\DesignPreviewGenerator — Runtime Preview pointer)
  → metadata
  → persist        (Capture\UserDesignStore — the only writer of `my-designs`)
  → repository.refresh()
  → registry.refresh()
  → dispatch DesignCaptured
```

## Hard rule: no bypass (Rule 8)

**No future feature may bypass the Capture Pipeline to persist content.** Community,
Marketplace, PRO and Portable Package must all reuse it. There is never a second
capture path.

## Guarantees

- **Normalization** strips all runtime ids and is deterministic, so the fingerprint
  is stable.
- **Fingerprint** is computed from the normalized payload only.
- **Preview** is a Runtime Preview route pointer — never a screenshot.
- **Persistence** is external: capture never touches BuilderState, the CommandBus
  or undo history.
- **Duplicates** are detected by fingerprint; only an explicit *Create Copy*
  overrides. *Update Existing* is reserved.

## Relationship to content changes

Editing a design's content never mutates the repository. Instead the user opens an
isolated [working copy](working-copy-contract.md) in the Builder and runs the
Capture Pipeline again, producing a **new** resource.
