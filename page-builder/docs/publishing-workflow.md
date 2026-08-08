# Publishing Workflow (Phase 26.50B, Rules 3 & 5)

> **Status: Deterministic pipeline over the FROZEN platform.**

Publishing **always** executes the same ordered, fail-fast pipeline. Nothing is
auto-corrected — the first failing step short-circuits and returns its field errors
(Rule 5).

## 1. The pipeline (`PublishingPipeline`)

```
Validate Manifest
   ↓
Validate Payload
   ↓
Validate Dependencies
   ↓
Validate Compatibility
   ↓
Generate Package Metadata
```

Each step is deterministic. The pipeline reuses the frozen `ManifestValidator` (no
second validator) and maps its field errors to steps:

| Step | Checks | Failure `step` |
|------|--------|----------------|
| Manifest | schema, rid, fingerprint, resource_type, provider, title, license, assets, author | `manifest` |
| Payload | non-empty renderable layout (sections) + preview metadata | `payload` |
| Dependencies | each dependency well-formed (resource id is a RID) | `dependencies` |
| Compatibility | compatibility declared + version semantic | `compatibility` |
| Package Metadata | build immutable `PackageMetadata` | — |

The pipeline is **pure**: it validates and computes metadata. It never renders a
preview, downloads, uploads, installs or resolves dependencies (Rules 12, 18).
Persistence, status and history are the service's job.

## 2. The workflow steps (`CommunityPublishingService`)

| Method | Transition | Notes |
|--------|-----------|-------|
| `draft(manifest, payload)` | → Draft | seeds a local draft; refuses to overwrite a published version (Rule 14) |
| `validate(rid)` | Draft → Validated | runs the validation steps |
| `package(rid)` | Validated → Packaged | generates package metadata |
| `publish(rid)` | Packaged → Published | version guard + history + manifest lock |
| `deprecate(rid)` | Published → Deprecated | keeps it searchable (Rule 9) |
| `archive(rid)` | Published/Deprecated → Archived | hidden, restorable (Rule 10) |
| `restore(rid)` | Archived → previous | back to where it was |

A failed validation returns a `PublishingResult` with `failed() === true`, the
failing `step`, and the field `errors` — the resource's status does **not** advance.

## 3. Version rules (Rules 7 & 8)

- Publishing requires `version` **strictly greater** than the last published version
  — never overwrite.
- Every publish creates a new package version and a new history entry.
- Content changes require a new fingerprint (the Capture Pipeline computes it);
  metadata-only changes keep the fingerprint.
- Compatibility, dependencies and minimum Builder version freeze at publish; a new
  publish creates a new version.

## 4. Preview (Rule 12)

Publishing reuses the one **Runtime Preview** engine keyed by RID. No screenshot
engine, thumbnail generator or second renderer is introduced.

See also: [community-publishing.md](community-publishing.md),
[resource-lifecycle.md](resource-lifecycle.md),
[publishing-history.md](publishing-history.md).
