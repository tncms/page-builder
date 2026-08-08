# Repository Index (Phase 26.50C)

The **Repository Index** is the metadata-only projection a repository publishes for
synchronization. It carries **no payload** — synchronization transports metadata, never
resource content (Rules 2, 16).

## 1. Entry shape (Rule 2)

Each `RepositoryIndexEntry` carries exactly:

| Field | Meaning |
| --- | --- |
| `rid` | Canonical resource identity (`<source>:<kind>:<resourceId>`). |
| `fingerprint` | Content digest of the resource (hex). Change ⇒ updated. |
| `version` | Semantic version. |
| `provider` | Owning repository/channel. |
| `status` | Exposure status: `published` / `deprecated` (active) or `archived` / `removed` (tombstone). |
| `manifest_summary` | `title`, `resource_type`, `category`, `tags`. |
| `package_metadata` | The published package metadata (never a downloadable bundle). |
| `compatibility_summary` | `page_builder`, `theme`, `minimum_builder_version`. |
| `updated_at` | Timestamp the metadata last changed. |
| `schema_version` | Manifest schema version (`1.0`). |

There is **no payload** and no other field. `status` is carried so change detection can
classify deprecation and archival (Rule 4).

## 2. Structure

```
RepositoryIndex
├── repository        (owning repository id)
├── mode              (full | incremental)
├── generated_at
├── entries[]         (active: published / deprecated)
└── removed[]         (tombstones: archived / removed)
```

## 3. Generation (Repository → Repository Index)

```php
$index = RepositoryIndex::fromStore($communityResourceStore, 'community');
```

- **Exposed** resources (`published` + `deprecated`) become active `entries`.
- **Archived** resources become `removed` tombstones (status `archived`), so archival
  is detectable even from a full snapshot.
- **Unpublished** work (`draft` / `validated` / `packaged`) is **never** indexed
  (Rule 13) — it stays purely local.

`fromStore` reads manifests and lifecycle only; it never reads payload.

## 4. Full vs incremental

- `RepositoryIndex::full($repo, $entries, $generatedAt, $removed)` — complete snapshot.
- `RepositoryIndex::incremental($repo, $entries, $removed, $generatedAt)` — a delta.

## 5. Fingerprint

`$index->fingerprint()` is a deterministic digest over each entry's
`rid@version#fingerprint:status`, so two indexes describing the same state compare
equal without inspecting payload.

## 6. Validation before acceptance (Rule 13)

`RepositoryIndexEntry::validationError()` gates metadata before it enters the cache: a
valid rid, a hex fingerprint, a semantic version, the shared schema version and a
declared compatibility (`page_builder` + `theme`). Compatibility is validated for
**presence** only — it is never resolved or enforced.
