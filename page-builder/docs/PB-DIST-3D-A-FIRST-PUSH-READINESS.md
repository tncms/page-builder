# PB-DIST-3D-A — First-Push Readiness Report

**Phase:** PB-DIST-3D-A — Live GitHub Bootstrap & First-Push Readiness Gate
**Reconciled by:** PB-DIST-3D-A-CLOSE (branch-authority fix PB-DIST-3D-A1 folded in)
**Certified baseline:** PB-DIST-3D-A1 commit `7e146e97202671d6a1455593d67d60f15f119252`
**Verdict:** `A. LOCAL FIRST-PUSH READINESS CLOSED — ONLY LIVE GATES REMAIN`

```
LOCAL READINESS = CLOSED
LIVE  READINESS = PENDING
```

> **PB-DIST-3D-A2 — canonical GitHub owner correction (identity only).** The project owner moved the
> canonical home to the organization `https://github.com/tncms`. The frozen target identity is corrected
> `repository_owner: thenguyen → tncms` and `owner_type: user → organization` (create endpoint
> `POST /user/repos → POST /orgs/tncms/repos`). `repository=page-builder`, `remote=origin`,
> `branch=main`, and the **source** identity `thenguyen/tn-cms` are unchanged. This is an identity
> correction: it does not reopen any LOCAL gate (`LOCAL READINESS = CLOSED` still holds) and marks no
> LIVE gate PASS. Because the projected tree carries the GitHub-facing identity (`config/publication.php`
> and the generated composer `support.source`), the A2 identity-corrected projection is re-certified
> deterministic (A == B) from the committed A2 source in the A2 isolated committed-baseline certification;
> the §4 hashes below remain the PB-DIST-3D-A1 (`7e146e9`) baseline evidence for the branch-authority
> and determinism mechanics, which A2 does not change.

> **PB-DIST-3D-A3 — live owner-type truth reconciliation.** PB-DIST-3D-LIVE inspected the real
> repository and obtained `owner.type = User`, disproving A2's assumption that `tncms` is an
> organization. A3 corrects `owner_type: organization → user` and the canonical bootstrap endpoint
> `POST /orgs/tncms/repos → POST /user/repos`; `repository_owner` stays `tncms` and every other identity
> is unchanged. Result: **ARCHITECTURE OWNER-TYPE CONFLICT = CLOSED**. The PB-DIST-3D-LIVE final
> re-certification against the corrected committed baseline is **PENDING** (must be re-run after the A3
> commit); A3 alone does not declare PB-DIST-3D-LIVE Verdict A. The live rows recorded in §8 reflect the
> read-only evidence gathered by PB-DIST-3D-LIVE and are subject to that final re-certification.

**No push performed. No live GitHub side effect performed.** The **live half** of the readiness
matrix (real repository inspect/create/emptiness/refs) is **PENDING** because no live GitHub
credential is available in this environment (`gh` not installed; `GITHUB_TOKEN`/`GH_TOKEN` empty; no
Git remote). No live evidence is fabricated.

---

## 1. Target identity (frozen — `config/publication.php`)

```
provider   = github
owner      = tncms                                          (was thenguyen — PB-DIST-3D-A2; account type User)
repository = page-builder
remote     = origin
branch     = main
owner_type = user            (PB-DIST-3C policy: User owner → POST /user/repos; A3-reconciled from live owner.type=User)
canonical  = https://github.com/tncms/page-builder
```

## 2. Live credential / auth method

```
gh CLI            : NOT installed
GITHUB_TOKEN      : empty
GH_TOKEN          : empty
git remotes       : none
→ live auth method: NONE AVAILABLE  →  live inspect/create not performed
```

The PB-DIST-3C authority accepts a credential only as **ephemeral request-time input**
(`GithubCredentials`). No token was supplied, none read from env/disk, none persisted.

## 3. Live repository state

```
NOT INSPECTED (no live credential). Classification PENDING.
```

## 4. Local standalone projection (from committed source `7e146e9`)

Built twice, independently, from an isolated committed worktree via
`ProjectionManager::firstProjection()` (PB-DIST-2 consuming the PB-DIST-1 exporter):

```
SOURCE_COMMIT           = 7e146e97202671d6a1455593d67d60f15f119252
STANDALONE_BRANCH       = main                                            ← native (PB-DIST-3D-A1)
STANDALONE_COMMIT (A)   = 3bee2d4b08443cfbccc9ed80ba67f79c36594dd1
STANDALONE_COMMIT (B)   = 3bee2d4b08443cfbccc9ed80ba67f79c36594dd1        (identical)
CONTENT_HASH            = 4cc63b903cae0a3e8c81513d37e940f815b30b7d554dc495aa80efbd9818906a  (A == B)
TREE_HASH               = 7f095fd5432342607aabff7c261354706d64625d
FILE_COUNT              = 1421
RELEASE_MANIFEST_SHA256 = 8dd17f642b5c4d6c1e2c6447bfaf4e089ac7bcfd7631be61197bc874efdc7a0f  (A == B)
STANDALONE_CLEAN        = YES
DETERMINISM             = YES (commit + content + manifest byte-identical across two builds)
```

## 5. Provenance proof

```
release-manifest.git_commit = 7e146e97202671d6a1455593d67d60f15f119252
source_commit               = 7e146e97202671d6a1455593d67d60f15f119252
→ PROVENANCE_source_matches = YES
```

The standalone working tree equals a fresh PB-DIST-2 projection (A == B, no drift/untracked).

## 6. Origin identity (local, via PB-DIST-3B `RemoteRepository` — no network)

```
addOrigin(https://github.com/tncms/page-builder.git)  on the disposable standalone candidate
origin state         = CONFIGURED_UNVERIFIED
observed identity    = github:tncms/page-builder
expected identity    = github:tncms/page-builder
→ ORIGIN_MATCHES_TARGET = YES
```

Origin is configured only on the disposable local standalone (then discarded); the TN CMS monorepo is
never touched. `set-url`/`remove`/rewrite are not available in the adapter.

## 7. Default-branch readiness — ✅ PASS (fixed by PB-DIST-3D-A1)

The prior conditional (`master` → `main` prep) is **RESOLVED**. As of PB-DIST-3D-A1
(commit `7e146e9`), the deterministic PB-DIST-2 projection **natively and reproducibly initializes on
`main`**:

```
PB-DIST-2 standalone projection initializes directly on main    (git init -b <branch>)
branch authority comes from the single publication contract      (config/publication.php default_branch)
ambient git init.defaultBranch CANNOT override it                (proven with a hostile config → still main)
NO post-init `branch -m master main` rename workaround exists
→ DEFAULT_BRANCH_MAIN = PASS
```

Guarded behaviorally + structurally by `PageBuilderProjectionBranchAuthorityTest` (10 tests).

## 8. First-push precondition matrix

| Row | Status | Evidence |
| --- | --- | --- |
| SOURCE_COMMIT_VALID | ✅ PASS | committed HEAD `7e146e9` |
| STANDALONE_PROJECTION_CERTIFIED | ✅ PASS | full PB-DIST green; A==B build |
| STANDALONE_REPO_CLEAN | ✅ PASS | `isClean()=YES` |
| PROVENANCE_VALID | ✅ PASS | manifest source == `7e146e9` |
| LOCAL_COMMIT_DETERMINISTIC | ✅ PASS | commit + content + manifest A==B |
| DEFAULT_BRANCH_MAIN | ✅ PASS | native `main` (PB-DIST-3D-A1); ambient-immune |
| PUBLICATION_IDENTITY_VALID | ✅ PASS | frozen `github:tncms/page-builder`, origin/main |
| ORIGIN_IDENTITY_CONTRACT | ✅ PASS | adapter `CONFIGURED_UNVERIFIED`, identity match |
| CREDENTIAL_BOUNDARY_VALID | ✅ PASS | ephemeral-only; manifest/config/URL secret-clean |
| NO_LOCAL_DRIFT | ✅ PASS | standalone clean; tree == fresh projection |
| NO_PUSH_IMPLEMENTATION | ✅ PASS | no push/upstream/force in tooling (allowlists frozen) |
| NO_PUSH_EXECUTED | ✅ PASS | zero push/upstream invoked |
| REMOTE_REPOSITORY_EXISTS | ⏳ PENDING (LIVE) | no live credential |
| REMOTE_REPOSITORY_EMPTY | ⏳ PENDING (LIVE) | no live credential |
| REMOTE_HAS_ZERO_UNEXPECTED_REFS | ⏳ PENDING (LIVE) | no live credential |
| NO_REMOTE_DRIFT | ⏳ PENDING (LIVE) | requires live ls-remote |
| NO_EXISTING_HISTORY | ⏳ PENDING (LIVE) | requires live inspection |

**Every LOCAL row is PASS.** Only LIVE rows remain PENDING.

## 9. Conflict cases (policy, enforced by PB-DIST-3C classification)

```
README-only / LICENSE-only / any history → NOT EMPTY → REFUSE (409-emptiness proof; never inferred)
wrong owner/name                          → REPOSITORY_IDENTITY_MISMATCH → REFUSE
archived / disabled                       → REPOSITORY_METADATA_MISMATCH → REFUSE
origin points elsewhere                   → adapter fail-closed (never rewrites)
local standalone dirty                    → projection REFUSE (repo.dirty)
release-manifest source mismatch          → provenance REFUSE
remote state unprovable / auth / network  → REFUSE (fail closed)
```

None is ever resolved with a force push.

## 10. Proof no push occurred / no push capability

```
git remotes on monorepo      = none
push / push -u / --force / --force-with-lease / --set-upstream : NOT invoked, NOT implemented
RemoteRepository allowlist    = {remote, ls-remote, rev-parse, status}                (no push/fetch/pull)
ProjectionRepository allowlist= {init, status, add, rm, commit, rev-parse, show, diff} (no push/remote)
no Pusher / FirstPush / Publisher class exists in the distribution tooling
```

## 11. Live external side-effect log

```
PERFORMED:      (none — no live GitHub API call, no ls-remote against a real remote)
NOT PERFORMED:  git push, git fetch, git pull, git merge, git rebase,
                GitHub API GET/POST, tag, GitHub Release, ZIP, GitHub Actions,
                real repository creation
LOCAL ONLY:     ProjectionManager.firstProjection (temp, discarded),
                RemoteRepository.addOrigin on the disposable standalone (discarded)
```

## 12. Remaining gates before PB-DIST-3D-B (all LIVE)

```
LIVE_AUTH                          — supply an ephemeral GitHub token authorized for the `tncms` account (User).
LIVE_REPOSITORY_INSPECTION         — classify the real repository.
LIVE_REPOSITORY_EXISTS_OR_CREATE   — exists, or create only if proven absent AND explicitly authorized.
LIVE_EMPTY_PROOF                   — prove genuinely empty (HTTP 409 evidence).
LIVE_ZERO_UNEXPECTED_REFS          — ls-remote proves zero unexpected heads/refs.
```

No LOCAL blocker remains.

## 13. Handoff

Next phase: **PB-DIST-3D-LIVE — Live Repository Bootstrap, Empty-State & Ref Verification**
(inspect/create real repo → prove identity → prove empty → prove zero refs → STOP, **no push**).
**PB-DIST-3D-B** (Authorized First Push) remains locked until the LIVE gate reaches Verdict A. It will
own exactly: final pre-push recheck → explicit operator authorization → one non-force push of `main` →
verify remote HEAD → verify remote tree/provenance → STOP. Incremental push remains PB-DIST-3E.
