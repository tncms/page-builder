# PB-DIST-3A — GitHub Remote Contract & Safety Audit

**Phase type:** Architecture / Audit / Safety-contract freeze.
**Remote side effects:** **ZERO.** No repository created, no remote configured, nothing
pushed/fetched/pulled/merged/rebased, no tag, no release, no ZIP, no GitHub Actions.
**HEAD at audit:** `9f18fdc` (PB-DIST-2), branch `ecommerce/e8-admin-ia-cleanup`, no Git remotes.

This document is the human-readable companion to
[`ADR-PB-GITHUB-REMOTE-ARCHITECTURE.md`](ADR-PB-GITHUB-REMOTE-ARCHITECTURE.md), which holds the
normative decision (status: **Accepted** at PB-DIST-3A closeout). It records what was audited,
the matrices, and the handoff to PB-DIST-3B.

---

## 1. Purpose

Freeze the exact safety contract for publishing the already-certified standalone Page Builder
repository to GitHub **before** any remote adapter is written. The permanent program rule holds:
**one phase = one new authority or one new side effect.** PB-DIST-3A introduces neither; it only
freezes the contract and locks it with a permanent guard.

## 2. Audited Surfaces

| Surface | Finding |
| --- | --- |
| `config/distribution.php` | `source_root` fixed literal `plugins/page-builder`; `forbidden` includes `.git/`; no credentials. |
| `tools/export/**` (12 files) | No remote/API/credential; only the composer stub carries a GitHub **support URL** (no userinfo). |
| `tools/projection/ProjectionRepository.php` | Sole Git seam; 8-verb allowlist; single `proc_open` (line 218); fail-closed on any other verb. |
| `tools/projection/ProjectionManager.php` | Orchestration; explicitly "never inits a remote, pushes, fetches, tags, or releases". |
| `tools/projection/ProjectionValidator.php` | Committed-source-only, destination-safety (no nesting), forbidden-path, final-tree equality. |
| `tools/projection/ProjectionProvenance.php` | Two non-overlapping provenance authorities; 10 known fields; no credential. |
| `tools/projection/ProjectionManifest.php` | Repository identity: `DEFAULT_SOURCE_REPOSITORY = thenguyen/tn-cms`; `projection_root = page-builder`. |
| `templates/composer.json.stub` | `support.source = https://github.com/tncms/page-builder` — target identity, no credential. |
| repo `.github/workflows` | Absent. No distribution/release workflow exists. |
| Git remotes | None configured (`git remote -v` empty). |

## 3. PB-DIST-0/1/2 Re-certification

- **Source authority:** the TN CMS monorepo, `source_root = plugins/page-builder` (fixed, never scanned). ✔
- **Export authority:** `ExportManager`, manifest-driven, consumed (never duplicated) by projection. ✔
- **Standalone Git authority:** `ProjectionRepository`, allowlist `init, status, add, rm, commit, rev-parse, show, diff`. ✔
- **Process-execution authority:** exactly one `proc_open`; no `shell_exec`/`system`/`popen`/`passthru`. ✔
- **Provenance authority:** `release-manifest.json` (7 fields) + deterministic commit message (3 fields). ✔
- **Fail-closed policy:** dirty source, drift, forbidden-extra, mid-flight op, non-empty destination all refuse. ✔
- **No remote capability:** no push/fetch/pull/remote/merge/rebase/reset/clean/clone method or verb. ✔

Existing suites green at baseline: **53 tests / 25,753 assertions / 1 skip** (symlink env).

## 4. Publishable Repository Authority

The **only** publishable object is a **PB-DIST-2 certified standalone projection**. The monorepo
is never a publication source or destination. `validateDestinationSafety()` structurally forbids a
destination equal to or nested with `plugins/page-builder`.

## 5. Repository Identity Matrix

| Role | Identity | Contents | Publishable |
| --- | --- | --- | --- |
| Source monorepo | `thenguyen/tn-cms` | many packages/plugins incl. `plugins/page-builder/` | **NEVER** |
| Standalone repo | `tncms/page-builder` | `.git/` + `page-builder/` (projected) | only source |

Frozen identity fields (distribution-tooling metadata, introduced as explicit config in 3B; owner
corrected `thenguyen → tncms` by PB-DIST-3D-A2; owner_type reconciled `organization → user` by
PB-DIST-3D-A3 per live `owner.type=User`): `provider=github`, `repository_owner=tncms`,
`repository_name=page-builder`, `remote_name=origin`, `default_branch=main`, `owner_type=user`.
Not Page Builder runtime config; no credentials.

## 6. Remote State Matrix

| State | Posture |
| --- | --- |
| `UNCONFIGURED` / `CONFIGURED_UNVERIFIED` | verify before any push |
| `REMOTE_EMPTY` | first-push eligible |
| `REMOTE_MATCHED` / `REMOTE_BEHIND` | incremental-push eligible |
| `REMOTE_AHEAD` / `REMOTE_DIVERGED` / `REMOTE_IDENTITY_MISMATCH` / `REMOTE_UNREACHABLE` / `AUTH_FAILED` | **REFUSE (fail closed)** |

(No `REMOTE_DIRTY` — not a Git concept.)

## 7. Operation Allow / Deny Matrix

| Operation | Local (`ProjectionRepository`) | Remote (future 3B+) |
| --- | --- | --- |
| `init`, `status`, `add`, `rm`, `commit`, `rev-parse`, `show`, `diff` | **ALLOW** | n/a |
| `push` (non-force, empty remote, main) | deny | 3D only, gated |
| `push` (non-force, matched/behind, main) | deny | 3E only, gated |
| `fetch`/ls-remote (read state) | deny | 3B/3E (read-only inspection) |
| `remote add`/`set-url` | deny | 3B/3C (config only, fail-closed on mismatch) |
| `pull`, `merge`, `rebase`, `reset --hard`, `clean`, `checkout -- .`, `stash` | **DENY** | **DENY (permanent)** |
| `push --force` / `--force-with-lease` | **DENY** | **DENY (permanent)** |
| remote branch deletion / history rewrite | **DENY** | **DENY (permanent)** |

## 8. Remote Drift Matrix

| Case | Classification |
| --- | --- |
| Manual README commit on remote | `REQUIRES_OPERATOR_RECONCILIATION` |
| Projected file edited on GitHub | `REQUIRES_OPERATOR_RECONCILIATION` |
| Auto-init README/LICENSE/.gitignore | `REQUIRES_OPERATOR_RECONCILIATION` (first push refuses; not empty) |
| Force-pushed foreign history | `REFUSE` |
| Branch deleted/recreated | `REFUSE` |
| Repo renamed/transferred | `REFUSE` |
| `origin` URL repointed | `REFUSE` |

Default posture: **FAIL CLOSED.** Automation never guesses which history wins.

## 9. Auth Boundary

```
SECRET INPUT  →  ephemeral process / auth layer  →  GitHub / Git transport
```

Credentials never enter `plugin.json`, `composer.json`, `release-manifest.json`, README metadata,
provenance, runtime config, database, payload, ZIP, Git history, or a remote URL. No `user:pass@`
URL is permitted. No authentication is implemented in 3A.

## 10. Provenance Chain

```
TN CMS source commit
  → release-manifest.source_commit (git_commit)
  → standalone deterministic projection commit (PB-DIST-2)
  → GitHub main HEAD (future 3D/3E)
```

PB-DIST-3F must later prove all four are consistent and the tree equals a fresh PB-DIST-2
projection. No third provenance authority is introduced.

## 11. Debt Register (summary)

| # | Finding | Class |
| --- | --- | --- |
| 1 | composer stub GitHub support URL = target identity | `DOCUMENT_ONLY` |
| 2 | source-repo string is provenance-only; publication identity is 3B config | `DEFERRED_3B` |
| 3 | standalone repo has no `origin` (correct for 3A) | `DEFERRED_3B` |
| 4 | push/fetch words in docstrings only | `FALSE_POSITIVE` |
| 5 | `.git` merge/rebase marker read is local drift detection | `FALSE_POSITIVE` |
| 6 | node_modules CI yml files | `FALSE_POSITIVE` |
| 7 | no remote-state verification yet | `DEFERRED_3E` |
| 8 | no auth layer yet | `DEFERRED_3B` |

**No `BLOCKER`, no `FIX_NOW`.** No production/runtime correction made or required.

## 12. Future-Phase Handoff → PB-DIST-3B

PB-DIST-3B (Local Remote Adapter) must:

1. Introduce a **separate `RemoteRepository`** authority (not extend `ProjectionRepository`),
   reusing the **one** Git process seam, with a remote-transport-only allowlist.
2. Take publishable identity from **explicit distribution config** (`provider`, `repository_owner`,
   `repository_name`, `remote_name=origin`, `default_branch=main`) — never ambient Git config.
3. Verify remote identity and classify remote state (§6) **read-only**; perform **no** push.
4. Fail closed on identity mismatch and on any URL containing `user:pass@`.
5. Keep the credential boundary (§9) intact — no credential in any artifact.
6. Leave first push (3D) and incremental push (3E) as still-separate, still-unbuilt contracts.

## 13. Zero-Side-Effect Statement

During PB-DIST-3A: no GitHub repository was created; no remote was configured or changed; no
push/fetch/pull/merge/rebase/reset/force-push occurred; no tag, no GitHub Release, no ZIP, no
GitHub Actions workflow was created; no credential was persisted; no concurrent worktree was
touched. Only read-only Git inspection and read-only source audit were performed, plus the
creation of this document, the ADR, and the permanent guard test.
