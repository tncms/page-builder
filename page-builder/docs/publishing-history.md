# Publishing History & Package Metadata (Phase 26.50B, Rules 6 & 11)

> **Status: Immutable history over the FROZEN platform.**

## 1. Package Metadata (Rule 6)

Every publish generates immutable `PackageMetadata` — **metadata only, never a
download bundle**:

| Field | Meaning |
|-------|---------|
| `package_id` | deterministic id: `pkg_` + sha256(rid @ version # fingerprint) |
| `rid` | the resource identity |
| `provider` | owning channel (`community`) |
| `published_version` | the semantic version published |
| `manifest_version` | the manifest schema version |
| `resource_fingerprint` | the content fingerprint at publish |
| `published_at` | publish timestamp (the only non-deterministic input) |
| `schema_version` | package-metadata schema (`1.0`) |

The `package_id` is deterministic: the same published content always yields the same
id.

## 2. Publishing History (Rule 11)

`PublishingHistory` is **append-only**. Each publish records one entry:

```json
{ "version": "1.0.0", "fingerprint": "…", "published_time": 1700000000,
  "manifest_version": "1.0", "status": "published", "package_id": "pkg_…" }
```

- `record()` returns a **new** history; entries are never mutated.
- There is **no rollback** and **no branching** — history only (Rule 11).
- `latest()`, `latestVersion()`, `versions()`, `hasVersion()` query the log.

## 3. Where it lives

History and current status are stored beside the manifest in the Community store as
`lifecycle.json` (status + `restore_to` + `history[]` + current `package`). The
manifest and payload files are never edited by a status change; publishing a new
version writes a new manifest and appends to the history.

## 4. Version monotonicity (Rule 7)

`publish()` refuses a version that is not strictly greater than the last published
version, so history is a strictly increasing, non-overwriting log.

See also: [publishing-workflow.md](publishing-workflow.md),
[resource-lifecycle.md](resource-lifecycle.md).
