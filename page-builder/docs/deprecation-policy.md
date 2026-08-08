# Deprecation & Archive Policy (Phase 26.50B, Rules 9 & 10)

> **Status: Lifecycle policy over the FROZEN platform.**

## 1. Deprecation (Rule 9)

A published resource may be **deprecated** (`publish → deprecate`). A deprecated
resource:

- **remains searchable** — it is still exposed by the `CommunityRepositoryProvider`;
- **remains installable** — insertion/import is unchanged;
- **shows a warning** — the resource's `distribution.deprecated` flag is `true` and
  `distribution.lifecycle_status` is `deprecated`, so the Library can surface a
  notice.

There is **no automatic migration**. Deprecation is a signal, not a transform. A
deprecated resource may be reinstated (`deprecate → publish`) or archived
(`deprecate → archive`).

## 2. Archive (Rule 10)

An archived resource (`published|deprecated → archive`):

- is **hidden by default** — the provider does not expose it;
- is **never deleted** — the manifest, payload and history remain on disk;
- is **restorable** — `restore()` returns it to the status it held before archiving
  (`published` or `deprecated`).

History is preserved across deprecation and archival.

## 3. What is NOT done (Rule 18)

- No automatic version migration.
- No deletion (there is no `deleted` state in this phase — it is reserved).
- No synchronization, notification, or remote propagation.

## 4. Provider exposure summary

| Status | Searchable | Installable | Warning |
|--------|-----------|-------------|---------|
| Published | yes | yes | no |
| Deprecated | yes | yes | yes |
| Archived | no | no | — |
| Draft / Validated / Packaged | no | no | — |

See also: [resource-lifecycle.md](resource-lifecycle.md),
[community-publishing.md](community-publishing.md).
