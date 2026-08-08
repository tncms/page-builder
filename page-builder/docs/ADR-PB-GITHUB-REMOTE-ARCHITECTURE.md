# ADR-PB-GITHUB-REMOTE-ARCHITECTURE

Page Builder Community Edition — GitHub Remote & Publication Architecture

## 1. Status

**Accepted** (PB-DIST-3A closeout).

This ADR freezes the *contract* for publishing the already-certified standalone Page
Builder repository to GitHub. It introduces **zero remote side effects**. No remote is
configured, no repository is created, nothing is pushed, tagged, or released. The first
implementation phase authorized to touch a real remote seam is **PB-DIST-3B (Local Remote
Adapter)**, and only under its own gate.

This ADR becomes **Accepted** only when the PB-DIST-3A architecture guard
(`PageBuilderGithubRemoteArchitectureGuardTest`) and the existing PB-DIST-0/1/2 suites are
green, and no `BLOCKER`/`FIX_NOW` debt remains open.

### 1.1 Amendment — PB-DIST-3D-A2 (canonical GitHub owner correction)

Before first publication the project owner corrected the canonical GitHub home to the
organization `https://github.com/tncms`. The frozen publication **target** identity is therefore
amended:

```
repository_owner : thenguyen → tncms
owner_type       : user      → organization   (create endpoint POST /user/repos → POST /orgs/tncms/repos)
```

Unchanged by this amendment: `repository = page-builder`, `remote_name = origin`,
`default_branch = main`, and the **source** monorepo identity
`ProjectionManifest::DEFAULT_SOURCE_REPOSITORY = thenguyen/tn-cms` (the source authority is never
republished). The composer `support.source` URL — which §5/§21 require to equal the frozen
`repository_owner/name` — moves to `https://github.com/tncms/page-builder`. No other safety rule in
this ADR is redesigned. This amendment introduces **no** live GitHub side effect.

### 1.2 Amendment — PB-DIST-3D-A3 (live owner-type truth reconciliation)

PB-DIST-3D-LIVE inspected the real repository and obtained authoritative GitHub metadata:

```
full_name = tncms/page-builder · owner.login = tncms · owner.type = User
```

1. **Why A2 chose `organization`:** the owner instruction described `tncms` as an "organization", and A2 froze `owner_type = organization` accordingly (never verified against live GitHub).
2. **What disproved it:** the live `owner.type = User` — `tncms` is a personal GitHub **User** account, not an Organization (`POST /orgs/tncms/repos` would 404).
3. **`repository_owner` stays `tncms`** — the GitHub repository identity and the GitHub account type are separate concerns; the owner correction from A2 is preserved.
4. **`owner_type` is corrected `organization → user`.**
5. **The canonical Community bootstrap endpoint is corrected `POST /orgs/tncms/repos → POST /user/repos`.**
6. **No other distribution architecture changes** — identity (`github:tncms/page-builder`), remote (`origin`), branch (`main`), source identity, composer support URL, and the generic owner-type routing in the API client are all unchanged.

Corrected frozen value:

```
owner_type : organization → user   (create endpoint POST /orgs/tncms/repos → POST /user/repos)
```

The API client still routes an explicit `org` owner type to `POST /orgs/{owner}/repos` generically; only the canonical Page Builder policy is pinned to `user`. This amendment uses already-recorded live evidence and introduces **no** live GitHub side effect.

## 2. Context

The distribution program has, in strict single-authority steps, produced:

| Phase | Authority added | Commit |
| --- | --- | --- |
| PB-DIST-0 | Distribution boundary freeze (what may be exported) | `1a78961` |
| PB-DIST-1 | Deterministic plugin exporter | `74f5b64` |
| PB-DIST-2 | Standalone repository projection + provenance + local Git | `9f18fdc` |

PB-DIST-2 already proves: a **fixed committed source** → **deterministic export** → a
**disposable local standalone Git repository** with a **deterministic tree and commit**,
fail-closed on drift/dirty/forbidden-extra, with **no remote methods anywhere**.

What remains before code can reach GitHub is a *contract*: which repository is publishable,
what identity is authoritative, what a remote may and may not do, and how divergence and
credentials are handled. Freezing that contract *before* writing any adapter is the entire
purpose of PB-DIST-3A. The permanent rule of the program applies:

> **ONE PHASE = ONE NEW AUTHORITY OR ONE NEW SIDE EFFECT.**
> Create repository ≠ Push. First push ≠ Incremental push. Incremental push ≠ Drift
> recovery. Build ZIP ≠ Publish ZIP. Tag ≠ GitHub Release. GitHub Release ≠ GitHub Actions.

## 3. Scope

**In scope (this ADR):** repository identity, remote name, default branch, remote state
model, first-push preconditions, incremental-push preconditions, remote-drift classification,
forbidden operations, local-Git vs GitHub-API authority separation, credential boundary,
provenance mapping, ambient-configuration prohibition, and the guards that lock all of it.

**Explicitly out of scope:** any code that talks to a remote, creates a repository, pushes,
fetches, tags, releases, builds a ZIP, or defines a GitHub Actions workflow. Those belong to
later, separately-gated phases (§20).

## 4. Existing Distribution Authorities (re-certified)

Re-audited at HEAD `9f18fdc`. Each authority is single and unchanged:

| Concern | Authority | Location |
| --- | --- | --- |
| Source authority | The TN CMS monorepo, `source_root = plugins/page-builder` (fixed literal) | `config/distribution.php` |
| Export authority | `ExportManager` driven only by the distribution manifest | `tools/export/` |
| Projection root | `page-builder/` inside the standalone repo | `config/distribution.php` |
| Standalone Git authority | `ProjectionRepository` — sole Git seam, 8-verb allowlist | `tools/projection/ProjectionRepository.php` |
| Process-execution authority | A single `proc_open` in `ProjectionRepository` | `tools/projection/ProjectionRepository.php:218` |
| Provenance authority | `release-manifest.json` (7 fields) + commit message (3 projection fields) | `tools/projection/ProjectionProvenance.php` |
| Cleanliness / forbidden / drift policy | `ProjectionValidator` + `ProjectionPlan` (fail-closed) | `tools/projection/` |

The local Git allowlist is `init, status, add, rm, commit, rev-parse, show, diff`. It contains
**no** `push`/`fetch`/`pull`/`remote`/`merge`/`rebase`/`reset`/`clean`/`clone`/`config`, and
`run()` rejects any other verb fail-closed. PB-DIST-3 **extends** this; it does not replace it.

## 5. Repository Identity

Two repositories are permanently distinguished:

| Role | Identity | Contains | Publishable? |
| --- | --- | --- | --- |
| **Source monorepo** | `thenguyen/tn-cms` (`ProjectionManifest::DEFAULT_SOURCE_REPOSITORY`) | `plugins/page-builder/` among many packages/plugins | **NEVER** |
| **Standalone Page Builder repo** | `tncms/page-builder` (target) | `.git/` + `page-builder/` (projected tree) | Only source, only when PB-DIST-2 certified |

The publishable identity fields (distribution-tooling metadata, **not** Page Builder runtime
config) are frozen conceptually as:

| Field | Frozen value | Source of truth |
| --- | --- | --- |
| `provider` | `github` | distribution config (3B) |
| `repository_owner` | `tncms` (was `thenguyen` — §1.1) | distribution config (3B) |
| `repository_name` | `page-builder` | distribution config (3B); already asserted by the composer support URL `https://github.com/tncms/page-builder` |
| `owner_type` | `user` (A2 set `organization`; §1.2 reconciled to live `User`) | PB-DIST-3C bootstrap policy → `POST /user/repos` |
| `remote_name` | `origin` | frozen — §7 |
| `default_branch` | `main` | frozen — §8 |

These live in **distribution-tooling** configuration when 3B introduces them, never in
`config/page-builder.php`, never in the database, never in a Page Builder payload. This ADR
does not require the real GitHub repository to exist and hardcodes no credential.

## 6. Standalone Repository Requirement

The GitHub publisher must **never** operate on the monorepo. The only publishable object is a
**PB-DIST-2 certified standalone projection**:

```
committed monorepo source commit
        │  PB-DIST-1 export  (deterministic)
        ▼
standalone repo:  .git/ + page-builder/   (PB-DIST-2 projection, clean, provenance-stamped)
        │  PB-DIST-3 remote layer  (future)
        ▼
GitHub: tncms/page-builder
```

`ProjectionValidator::validateDestinationSafety()` already rejects a destination equal to or
nested with the source root, so the monorepo cannot be a projection destination. The remote
layer must inherit this: it accepts a validated standalone repo root, never a monorepo path.

## 7. Remote Identity (remote name)

**Canonical remote name: `origin`.** Rationale: it is the Git default and the least-surprising
name for the single upstream. Policy:

- Exactly **one** remote is expected. Additional remotes are out of contract.
- If `origin` exists with an **unexpected URL** → **FAIL CLOSED**. The tooling must never run
  `git remote set-url origin …` to "fix" it. Re-pointing an existing origin is a recovery
  operation reserved for a future, explicitly-authorized phase/ADR.
- A remote named anything other than `origin` is not adopted implicitly.

## 8. Default Branch

**Authoritative publication branch: `main`.** It is **explicit distribution configuration**,
never inferred from whichever branch the monorepo happens to be on (the working checkout is on
`ecommerce/e8-admin-ia-cleanup`, which is irrelevant to publication).

- Branch identity comes from frozen distribution config, not ambient `HEAD`.
- First push (3D) **may create** `main` on an empty remote.
- Incremental push (3E) targets **only** `main`; no other branch.
- The remote default branch must equal `main`; a mismatch is drift (§12).

## 9. Remote State Model

The future remote layer must classify the remote into exactly one state before acting. There
is no "REMOTE_DIRTY" — dirtiness is a working-tree concept and does not apply to a remote.

| State | Meaning | Allowed next action |
| --- | --- | --- |
| `UNCONFIGURED` | No `origin` locally | Configure origin (3B/3C), then re-classify |
| `CONFIGURED_UNVERIFIED` | `origin` URL set, identity not yet verified | Verify identity (§12); do not push |
| `REMOTE_EMPTY` | Repo exists, publication branch has no commit | **First push** eligible (3D) |
| `REMOTE_MATCHED` | Remote branch HEAD == expected known projected commit | No-op or eligible incremental push (3E) |
| `REMOTE_BEHIND` | Remote is an ancestor of the next valid projection | Incremental push eligible (3E) |
| `REMOTE_AHEAD` | Remote has commits unknown locally | **REFUSE** — operator review |
| `REMOTE_DIVERGED` | Local and remote histories diverged | **REFUSE** — operator reconciliation |
| `REMOTE_IDENTITY_MISMATCH` | Remote URL/identity ≠ configured identity | **REFUSE** — fail closed |
| `REMOTE_UNREACHABLE` | Remote cannot be queried | **REFUSE** — no side effect |
| `AUTH_FAILED` | Credentials insufficient/invalid | **REFUSE** — no side effect |

## 10. First Push Contract (PB-DIST-3D preconditions)

A first push must eventually require **ALL** of:

1. source commit validated (exact HEAD, committed-only);
2. standalone projection certified (PB-DIST-2 final-tree equality);
3. standalone repository clean (no drift, no mid-flight op);
4. projection provenance valid (release-manifest ↔ commit message);
5. repository identity exact (`tncms/page-builder`);
6. remote identity exact (`origin` → expected URL);
7. remote publication branch proven **empty** (`REMOTE_EMPTY`);
8. no existing unexpected history;
9. explicit target branch (`main`);
10. **non-force** push.

If the remote already contains history, **first push MUST REFUSE** and the situation becomes
an existing-remote case for PB-DIST-3E or operator review.

## 11. Incremental Push Contract (PB-DIST-3E preconditions)

Safe path only:

```
known remote projection commit
   → verify remote HEAD
   → verify local projection ancestry/provenance
   → remote HEAD == expected previous projection
   → push next deterministic projection commit  (non-force, main)
```

If `remote HEAD != expected previous projection` → **STOP**. The tooling must never
automatically `pull`, `merge`, `rebase`, `reset`, `force-push`, or `force-with-lease`. Remote
divergence must be **operator-visible**; automation never guesses which history wins.

## 12. Remote Drift Policy

Drift is any remote state that is not `REMOTE_EMPTY` (before first push) or exactly the
expected previous projection (before incremental push). Default posture: **FAIL CLOSED**.

| Case | Example | Classification |
| --- | --- | --- |
| A | Remote has a manual README commit | `REQUIRES_OPERATOR_RECONCILIATION` |
| B | A projected file edited directly on GitHub | `REQUIRES_OPERATOR_RECONCILIATION` |
| C | Repo auto-initialized with README/LICENSE/.gitignore | `REQUIRES_OPERATOR_RECONCILIATION` (first push refuses; not empty) |
| D | Someone force-pushed another history | `REFUSE` (`REMOTE_DIVERGED`/`REMOTE_AHEAD`) |
| E | Remote branch deleted/recreated | `REFUSE` — re-verify identity + state |
| F | Repository renamed/transferred | `REFUSE` (`REMOTE_IDENTITY_MISMATCH`) |
| G | `origin` URL now points elsewhere | `REFUSE` (`REMOTE_IDENTITY_MISMATCH`) |

`SAFE` is reserved for `REMOTE_EMPTY` (first push) and `REMOTE_MATCHED`/`REMOTE_BEHIND`
(incremental push). Everything else is `REFUSE` or `REQUIRES_OPERATOR_RECONCILIATION`.

## 13. Local Git Authority (layering for PB-DIST-3B)

`ProjectionRepository` remains the **local, read-or-local-write** Git seam and must not gain a
remote verb. Remote operations are a **separate, narrowly-scoped** authority:

```
ProjectionManager → ProjectionRepository → (single) Git process runner
RemoteManager     → RemoteRepository     → same single Git process runner
```

Decision: **PB-DIST-3B introduces a separate `RemoteRepository`** (not an extension of
`ProjectionRepository`), because remote transport is a distinct capability with distinct
failure modes. It must reuse the **one** underlying Git process-execution authority (the
`proc_open` seam), with its own allowlist limited to remote-transport verbs, each individually
authorized. Two overlapping Git-execution authorities are forbidden.

## 14. GitHub API Boundary

Git transport and the GitHub API are **different authorities**:

| Authority | Operations | Phase |
| --- | --- | --- |
| Git transport | remote inspection, branch refs, push | 3B–3E |
| GitHub API | create repository, read repo metadata, create release, upload assets | 3C, 5B, 5C |

They must not be conflated in one class. Creating a repository (API) is a distinct side effect
from pushing to it (transport), and each gets its own phase.

## 15. Credential Boundary

GitHub credentials must **never** appear in: `plugin.json`, `composer.json`,
`release-manifest.json`, generated README metadata, projection provenance, Page Builder runtime
config, the database, any Page Builder payload, a ZIP, Git history, or a remote URL containing
a plaintext token.

```
SECRET INPUT  →  ephemeral process / auth layer  →  GitHub / Git transport
(never persisted, never projected, never exported)
```

Future auth mechanisms (not implemented here) may include: an authenticated `gh` CLI
environment, an environment variable, an OS credential manager, a Git credential helper, or a
GitHub App in later automation. PB-DIST-3A implements no authentication. Guards assert no
credential field and no `user:pass@` URL exists in tooling, config, manifest, or provenance.

## 16. Provenance Mapping

Remote publication adds **no third provenance authority**. It only maps the existing chain to a
remote ref:

```
TN CMS source commit
   → release-manifest.source_commit (git_commit)
   → standalone deterministic projection commit  (PB-DIST-2)
   → GitHub branch HEAD (main)
```

PB-DIST-3F must be able to prove: `GitHub main HEAD == expected standalone projection commit`,
that commit's tree `== fresh PB-DIST-2 projection`, and its release manifest `== expected TN
CMS source provenance`.

## 17. Failure / Fail-Closed Policy

Every remote uncertainty resolves to **no side effect**: `REMOTE_UNREACHABLE`, `AUTH_FAILED`,
`REMOTE_AHEAD`, `REMOTE_DIVERGED`, and `REMOTE_IDENTITY_MISMATCH` all **refuse** and surface to
the operator. The automation never adopts unknown remote history and never rewrites remote
history to make a push succeed.

## 18. Forbidden Operations (permanent denylist)

The automated Page Builder distribution tooling must **NEVER** perform:

```
git pull            git push --force
git merge           git push --force-with-lease
git rebase          remote branch deletion
git reset --hard    remote history rewrite
git clean           automatic divergence resolution
git checkout -- .   automatic adoption of unknown remote history
git stash
git restore <unrelated>
```

Any future recovery tooling requires its own phase and ADR.

## 19. Community / Release Boundary

This ADR governs **remote publication of the standalone source repository** (3B–3F). Release
artifacts (ZIP — 4A/4B/4C), tags (5A), GitHub Releases (5B/5C), and GitHub Actions automation
(6) are **later, separate** authorities and are explicitly not decided here. Building a ZIP is
not publishing it; a tag is not a release; a release is not a workflow.

## 20. Future Extension Seams

| Seam | Phase | New authority / side effect |
| --- | --- | --- |
| `RemoteRepository` (transport verbs, reuses the one process runner) | 3B | Local remote adapter (no network) |
| GitHub API repo-create client | 3C | Repository creation |
| First-push orchestrator | 3D | First network write |
| Incremental-push + drift guard | 3E | Incremental write + refusal |
| Remote publication certification | 3F | Proof only |
| Release manifest / ZIP builder / install cert | 4A–4C | Artifact build |
| Tag / Release / asset verification | 5A–5C | Release side effects |
| GitHub Actions automation | 6 | CI automation |

## 21. Technical Debt Register

| # | Finding | Classification | Note |
| --- | --- | --- | --- |
| 1 | `composer.json.stub` hardcodes `support.source = https://github.com/tncms/page-builder` | `DOCUMENT_ONLY` | Not a credential; asserts the target identity. The frozen `repository_owner/name` (§5, amended §1.1) must equal this. No `user:pass@`. |
| 2 | `ProjectionManifest::DEFAULT_SOURCE_REPOSITORY = thenguyen/tn-cms` is a default, not enforced against the real monorepo remote | `DEFERRED_3B` | Publication identity must be explicit distribution config in 3B; source-repo string is provenance-only today. |
| 3 | Standalone repo is created via local `git init`; it has no `origin` | `DEFERRED_3B` | Correct for 3A (no remote). Origin config is the 3B authority. |
| 4 | Docstrings in `ProjectionRepository`/`ProjectionManager`/`ProjectionReport` mention push/fetch/remote | `FALSE_POSITIVE` | Prose only; no invocation. `run()` allowlist + guard prove non-reachability. |
| 5 | `operationInProgress()` reads local `.git` merge/rebase markers | `FALSE_POSITIVE` | Local drift detection, not a remote op. |
| 6 | node_modules ship `.travis.yml`/`.github/FUNDING.yml` | `FALSE_POSITIVE` | Third-party deps, excluded from export (`node_modules/**`); not the distribution pipeline. |
| 7 | No remote state verification exists yet | `DEFERRED_3E` | State model frozen here (§9); implementation is 3B–3E. |
| 8 | No credential/auth layer exists | `DEFERRED_3B` | Intentional; boundary frozen (§15), implementation later. |

**No `BLOCKER` and no `FIX_NOW` findings.** No architecture correction is required in 3A.

## 22. Architecture Guards

`tests/Feature/PageBuilder/PageBuilderGithubRemoteArchitectureGuardTest.php` permanently locks:

1. PB-DIST-2 remains the standalone-projection authority (consumes the one exporter).
2. Local Git allowlist == the frozen 8 verbs; no remote/rewriting verb, ever.
3. `ProjectionRepository` exposes no remote/history-rewriting method.
4. Exactly one `proc_open`; no `shell_exec`/`system`/`popen`/`passthru`/`pcntl_exec`.
5. No `api.github.com`, `GITHUB_TOKEN`, `GH_TOKEN`, `curl_*`, socket calls, `git push/fetch/pull/remote`, or `--force[-with-lease]` in any tool.
6. No `user:pass@` URL and no `@github.com` credential in tooling or templates.
7. No credential field in config, `plugin.json`, manifest, or provenance.
8. Repository identity is explicit; `config`/`remote` are not allowlisted (no ambient inference); source identity ≠ projection root.
9. Monorepo rejected as projection destination; `.git/` forbidden from the projected tree.
10. No remote/GitHub/publisher adapter class and no distribution GitHub Actions workflow exist yet.

## 23. Acceptance Gate

Accept only when: the guard above is green; PB-DIST-0/1/2 suites remain green; no
`BLOCKER`/`FIX_NOW` debt is open; and ZERO GitHub/remote mutation occurred.

## 24. Decision

Freeze the GitHub remote/publication contract as specified in §5–§20. The **only** publishable
object is a PB-DIST-2 certified standalone projection of `thenguyen/tn-cms`, published to
`tncms/page-builder` on remote `origin`, branch `main`, via a **future** separate
`RemoteRepository` transport authority that reuses the single Git process seam. First push and
incremental push are separate contracts; divergence and unknown history fail closed; no
automatic merge/rebase/reset/force-push; credentials never enter any artifact; identity is
explicit, never ambient. PB-DIST-3A performs **no** remote side effect.

Status is **Accepted**: the acceptance gate (§23) is satisfied — the PB-DIST-3A architecture
guard is green, the PB-DIST-0/1/2 suites remain green, no `BLOCKER`/`FIX_NOW` debt is open, and
zero GitHub/remote mutation occurred.
