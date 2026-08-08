# PB-DIST-3C — GitHub Repository Bootstrap Contract

**Phase:** PB-DIST-3C — GitHub Repository Bootstrap
**Baseline:** PB-DIST-3B (local remote adapter), commit `abbb6e9`
**Status:** Implemented & certified locally — **live GitHub bootstrap withheld**
**Scope:** inspect, and (only when explicitly authorized) create the standalone
`github.com/tncms/page-builder` repository. **No source transfer. First Push is PB-DIST-3D.**

This contract extends — never weakens — [ADR-PB-GITHUB-REMOTE-ARCHITECTURE](ADR-PB-GITHUB-REMOTE-ARCHITECTURE.md)
(§14 GitHub API Boundary, §15 Credential Boundary, §20 Extension Seams) and the
[PB-DIST-3A contract](PB-DIST-3A-GITHUB-REMOTE-CONTRACT.md).

---

## 1. Authority separation (hard boundary)

The GitHub API is a **separate authority** from Git transport. They share no code path.

```
GitProcessRunner                     (tools/git)      — the ONE Git process authority (PB-DIST-3B)
  └─ ProjectionRepository/RemoteRepository            — local Git + permitted transport inspection

GithubApiTransport (interface)       (tools/github)   — the ONE GitHub HTTP seam (PB-DIST-3C)
  └─ CurlGithubApiTransport                           — the only HTTP primitive (cURL, HTTPS-only)
     └─ GithubApiClient                               — typed REST calls, attaches auth at request time
        └─ GithubRepositoryBootstrap                  — inspect / plan / explicit create
```

- The GitHub layer executes **no Git command** (no `proc_open`/`shell_exec`/`git …`).
- The Git/transport layer calls **no GitHub HTTP** (no `api.github.com`, no `Tools\Github`).
- No Page Builder runtime code under `plugins/page-builder/src/` references this tooling.

Guards: `PageBuilderGithubRepositoryBootstrapGuardTest`, and the advanced §9 of
`PageBuilderGithubRemoteArchitectureGuardTest`.

## 2. Publication identity (consumed, never re-derived)

Identity is the frozen `config/publication.php` (PB-DIST-3B `PublicationTarget`), projected into API
terms by `GithubRepositoryIdentity::fromTarget()`:

```
provider = github   owner = tncms   repository = page-builder   owner_type = user
```

It is **never** inferred from the current Git remote, CWD, monorepo name, authenticated GitHub user,
ambient Git config, or an API response. API responses are only **validated** against this identity
(`matchesApiRepository()`, case-insensitive full-name); they never define it. There is no second
identity authority.

## 3. Credential boundary

A token is **ephemeral input only** (`GithubCredentials`): explicit constructor input, no env/disk
fallback, no default. It is attached **only at HTTP request time** as an `Authorization: Bearer …`
header and is never persisted, serialized, logged, or copied into a report, plan, config, manifest,
provenance, exception, or URL.

- `__toString`/`__debugInfo` redact; `__serialize` throws (`credentials.serialize`).
- `GithubException::redact()` strips `Authorization:`/`Bearer`/`token …` values, `gh*_…` tokens, and
  `scheme://user:secret@host` userinfo at construction.

Guards: `PageBuilderGithubCredentialBoundaryTest`.

## 4. HTTP transport

`GithubApiTransport` is the single seam; `CurlGithubApiTransport` is the only implementation with a
real network primitive. It speaks **HTTPS to `api.github.com` only** (scheme/host asserted before any
socket opens), verifies TLS, sends explicit methods/headers, decodes JSON deterministically, and
fails closed (`GithubException`) on transport error. Automated tests inject a fake transport — **no
real GitHub network is ever required.**

## 5. Repository-state model (only what 3C can prove)

`GithubRepositoryState`:

```
REPOSITORY_NOT_FOUND          REPOSITORY_IDENTITY_MISMATCH   AUTH_FAILED
REPOSITORY_EXISTS_EMPTY       REPOSITORY_METADATA_MISMATCH   FORBIDDEN
REPOSITORY_EXISTS_NONEMPTY    API_UNREACHABLE                RATE_LIMITED
                                                             UNKNOWN_FAILURE
```

The PB-DIST-3E history states (MATCHED/BEHIND/AHEAD/DIVERGED) are **not** claimed here.

## 6. Inspection & the not-found-vs-forbidden policy (§9)

Inspection issues **only GET requests** — it never creates.

- `200` → validate identity → validate metadata → prove emptiness.
- `401 → AUTH_FAILED`; `403 → RATE_LIMITED` (when `x-ratelimit-remaining: 0` or a rate-limit
  message) else `FORBIDDEN`; `429 → RATE_LIMITED`; `5xx`/network `→ API_UNREACHABLE`; malformed JSON
  on a 200 `→ fail closed (api.invalid_json)`; otherwise `UNKNOWN_FAILURE`.
- `404 → REPOSITORY_NOT_FOUND`, **conservatively**: a 404 may conceal a private repository, so absence
  is *not proven* by 404 alone. Creation therefore additionally requires explicit authorization.

## 7. Empty-repository proof (§10)

Emptiness is **never inferred** from a missing README, an absent default branch, or a single 404.
The strongest practical evidence is used: GitHub returns **HTTP 409 "Git Repository is empty"** for a
repository with no commits. A `200` commit list proves non-emptiness. Any outcome that cannot prove
emptiness yields the conservative `REPOSITORY_EXISTS_NONEMPTY` with `readyForFirstPush=false`.

`readyForFirstPush` is **derived** only for a proven-empty, identity/metadata-matching repository —
and is never acted upon here.

## 8. Explicit creation (§11) & metadata policy (§12)

Creation requires a mutation call and **all** preconditions: valid explicit target, provider GitHub,
prior inspection classifying the repo safely **missing**, an ephemeral credential, and the caller's
explicit `authorizedToCreate=true`. Ordinary inspection never creates. Existing repositories are never
recreated, deleted, renamed, transferred, or "repaired".

Frozen bootstrap metadata (`GithubRepositoryMetadata::communityPolicy`):

```
visibility = public          has_issues = true            auto_init = false   ← critical invariant
description = plugin.json     has_projects = false         gitignore_template = (none)
homepage = (none)            has_wiki = false             license_template  = (none)
owner_type = user            has_discussions = false
```

`auto_init=false` (and the absence of gitignore/license templates) guarantees GitHub creates **no**
README/license/starter commit — PB-DIST-2 owns the tree and PB-DIST-3D performs the first push into a
proven-empty repository. Description is sourced from `plugin.json` (the same authority PB-DIST-1 uses
for the composer `description`); `owner_type=user` reflects that `tncms` is a GitHub **User** account
(PB-DIST-3D-LIVE proved `owner.type=User`; PB-DIST-3D-A3 reconciled the earlier A2 organization
assumption — the canonical Community target creates via `POST /user/repos`, never `POST /orgs/tncms/repos`;
the endpoint is derived from the explicit target owner type, never from the authenticated user, and the
client still routes an explicit `org` owner type to `POST /orgs/{owner}/repos` generically). The live
`tncms/page-builder` repository already exists and is empty, so this policy never triggers creation.

After a successful `201`, the bootstrap **re-inspects** and requires `REPOSITORY_EXISTS_EMPTY`;
anything else (e.g. the token authenticated as a different owner) fails closed
(`bootstrap.create_unverified`) so a non-target is never handed to PB-DIST-3D.

## 9. Existing-repository policy (§13) & idempotency (§15)

Incompatible existing state fails closed with no side effect: wrong owner/name
(`REPOSITORY_IDENTITY_MISMATCH`), archived/disabled or incompatible visibility
(`REPOSITORY_METADATA_MISMATCH`), non-empty or unprovable emptiness (`REPOSITORY_EXISTS_NONEMPTY`).

```
missing + explicit create → inspect NOT_FOUND → create once → re-inspect EXISTS_EMPTY
existing valid empty      → no duplicate creation
existing non-empty        → no mutation; not first-push-ready
identity/metadata mismatch→ fail closed, no mutation
```

## 10. Planning / dry-run (§16)

`GithubRepositoryBootstrap::plan()` performs inspection only and yields one of:
`NO_ACTION`, `CREATE_REPOSITORY`, `REFUSE_NONEMPTY`, `REFUSE_IDENTITY`, `REFUSE_METADATA`,
`REFUSE_INSPECTION`. Plans and reports contain no credentials.

## 11. Out of scope (later phases)

No push/fetch/pull/merge/rebase/force/tracking-branch/origin-rewrite; no tag, GitHub Release, asset
upload, ZIP, Marketplace artifact, or GitHub Actions/workflow. Origin configuration is unchanged
(PB-DIST-3B already owns safe origin behavior; 3C does not require it).

## 12. Live status

Automated certification uses a fake transport exclusively; the real `tncms/page-builder`
repository is **not** created by this phase.

```
IMPLEMENTED AND CERTIFIED LOCALLY — LIVE GITHUB BOOTSTRAP PENDING
```

## 13. PB-DIST-3D handoff

PB-DIST-3D alone owns **First Push**: prove remote empty → prove local standalone provenance →
verify exact origin → push deterministic `main` → verify remote HEAD → certify publication provenance.
None of that is performed here.
