# PB-DIST-2 — Standalone Repository Projection & Provenance Lock

Status: implemented, developer-side tooling only. No runtime, routes, or service-provider wiring.
No GitHub repository, remote, push, tag, release, ZIP, or CI is created by this phase.

This document is the contract for projecting the committed TN CMS monorepo Page Builder source into
a **local** standalone Git repository, deterministically and auditably, by consuming the PB-DIST-1
exporter. It stops at a valid local standalone repository (Stage 23 handoff).

---

## 1. Source authority

```
MONOREPO       = SOURCE AUTHORITY        (only place development happens)
STANDALONE REPO = GENERATED PROJECTION   (disposable, reproducible)
GITHUB RELEASE  = FUTURE DISTRIBUTION     (not this phase)
```

The monorepo is the single source of truth. A projection is always reproducible from:

```
source commit + distribution manifest + exporter version + injected metadata
```

The standalone repository is never a second source of truth and is never hand-edited as a workflow.

## 2. Projection role

PB-DIST-2 is a thin orchestration layer around the PB-DIST-1 exporter (DIST2-02: PB-DIST-1 is the
only exporter — no export logic is duplicated). Every run:

1. validates the committed source at an exact commit;
2. invokes the PB-DIST-1 exporter into a fresh temp tree;
3. builds the expected tree + provenance;
4. compares against the current standalone working tree (metadata excluded);
5. produces a deterministic plan;
6. fails closed on unknown extras;
7. applies exact `ADD`/`UPDATE`/`DELETE`;
8. validates the final tree equals the export;
9. stages exactly the touched paths and makes one deterministic local commit.

The projected tree lives under the fixed projection root `page-builder/` inside the standalone
repository (this mirrors the PB-DIST-1 exporter output faithfully; PB-DIST-2 does not rewrite it).

## 3. First projection

```
validate source commit
→ export (PB-DIST-1) into temp
→ validate projection (paths, forbidden, symlinks)
→ dry-run plan (all ADD against an empty destination)
→ init local standalone repo
→ copy projection
→ validate final tree == export
→ stage exact projected paths
→ deterministic local commit
```

Requires an empty (or absent) destination. No remote, no push.

## 4. Update projection

```
validate standalone repo clean (fail closed on manual drift / mid-flight op)
→ validate provenance-bearing source commit
→ export new projection into temp
→ compare → plan (ADD/UPDATE/DELETE/UNCHANGED, fail closed on FORBIDDEN_EXTRA)
→ synchronize exact operations (stale projected files are deleted, never preserved)
→ validate final tree == export
→ stage exact touched paths
→ deterministic local commit
```

## 5. Dry-run

Any synchronization can be previewed with `dryRun()`, which computes the same deterministic plan
and provenance **without writing to the standalone tree or committing** (DIST2-07). The dry-run plan
equals what a subsequent apply performs.

## 6. Drift policy

| Situation | Classification | Action |
|---|---|---|
| Working tree ≠ HEAD (hand edit) | manual drift | **fail closed** (`repo.dirty`) |
| Committed file outside `page-builder/`, not `.git` | unknown extra | **fail closed** (`forbidden_extra`) |
| Committed file under `page-builder/` not in export | stale projected | planned **delete** |
| `.git` subtree | standalone metadata | preserved, never planned |
| Merge/rebase/cherry-pick in progress | mid-flight | **fail closed** (`repo.midflight`) |

Manual modification of a projected file is caught by the clean-repository check before planning, so
hand edits are never silently overwritten.

## 7. Provenance

Two non-overlapping authorities (no field is duplicated):

- **`page-builder/release-manifest.json`** (exporter-generated, committed on-disk authority):
  `git_commit` (= source_commit), `version` (= plugin_version), `manifest_version`
  (= distribution_manifest_version), `export_version` (= exporter_version), `generated_at`,
  `content_hash`, `file_count`.
- **Deterministic commit message** (projection-layer authority): `source_repository`,
  `source_branch`, `projection_tool_version`, plus a mirror of the release-manifest fields for a
  permanent, human-auditable record.

`generated_at` and the standalone commit date are **derived from the source commit's committer
date**, never wall-clock — this is what makes no-op and A→B==fresh-B hold byte-for-byte.

## 8. Local Git operations

All local Git process execution is isolated in `ProjectionRepository`, which permits only:

```
init  status  add (exact paths)  rm (exact paths)  commit  rev-parse  show  diff
```

Staging uses `git add --pathspec-from-file=- --pathspec-file-nul` over the exact touched paths —
never `git add -A` / `git add .`.

## 9. Forbidden Git operations

Not reachable — no method exposes them and the allowlist rejects them fail-closed:

```
push  fetch  pull  merge  rebase  reset  clean  remote  clone  cherry-pick  gc  (any force)
```

There is therefore **no push capability** anywhere in the projection tool.

## 10. Destination safety

The destination must be empty or a valid standalone repository. Rejected:

```
destination == source        destination inside source        source inside destination
path traversal (..)          absolute / drive / UNC paths      symlink / junction escape
non-empty arbitrary dir      (never recursively wiped as a shortcut)
```

## 11. Atomic synchronization

The export is built and validated in a temp directory first. Only after the plan is validated are
exact operations applied to the standalone tree; on any failure the tool throws before committing,
so a failed apply never produces a commit.

## 12. Determinism

Same source commit + identical metadata ⇒ byte-identical working tree, byte-identical provenance,
identical plan, identical Git tree object, and (with injected author/committer identity and dates)
identical commit hash.

## 13. No-op behaviour

Projecting the same source twice yields `0 ADD / 0 UPDATE / 0 DELETE` and **no commit** (no empty
commits).

## 14. Failure policy

Every abort is a `ProjectionException` carrying the exact violated rule. Validation precedes any
git write, so a thrown exception means nothing was committed.

## 15. Security boundaries

Path/traversal/symlink safety (Stage 18), committed-source-only (Stage 11), destination nesting
checks (Stage 18), forbidden-fragment rejection (PB-DIST-1 authority), and the git allowlist all
fail closed. The tool never writes outside the destination root.

## 16. Future GitHub handoff

PB-DIST-2 stops at a valid local standalone Git repository and may output: standalone repo path,
standalone commit hash, source-commit mapping, content hash, and a projection report. Creating a
GitHub repository, configuring a remote, authenticating, pushing, tagging, releasing, or building a
ZIP belong to future phases (PB-DIST-3+). See Stage 23.

## 17. Acceptance evidence

- Permanent tests under `tests/Feature/PageBuilder/`:
  `PageBuilderStandaloneProjectionTest`, `PageBuilderProjectionDriftTest`,
  `PageBuilderProjectionProvenanceTest`, `PageBuilderProjectionDryRunTest`,
  `PageBuilderProjectionRepositoryGuardTest` (plus `ProjectionTestSupport` fixture).
- Committed-baseline certification (Stage 21) runs, from a clean isolated worktree at the PB-DIST-2
  commit: PB-DIST-0 guard, PB-DIST-1 tests, PB-DIST-2 tests, a real local standalone projection of
  the full plugin, a no-op re-projection, an A→B update, and fresh-B tree equivalence.
