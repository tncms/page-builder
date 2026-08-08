# ADR-PB-DISTRIBUTION-ARCHITECTURE — Standalone Community Distribution Boundary

**Status:** Accepted (architecture freeze only — no exporter/workflow/ZIP implemented)
**Date:** 2026-08-06
**Phase:** PB-DIST-0 (distribution boundary architecture lock)
**Depends on (frozen):** PB-COMMUNITY-ARCHITECTURE-FREEZE (Accepted), the five foundation ADRs (Owner / Data / Runtime / Component / Behavior), ADR-PB-RENDER-SOURCE-001, ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION.
**Supersedes / amends:** nothing. First distribution ADR. Introduces no runtime change.

---

## 1. Status

Accepted as an **architecture freeze only**. This ADR defines *what may ever leave the
monorepo as the standalone Community Page Builder* and *what must never leave it*. It does
**not** implement any exporter, GitHub workflow, release ZIP, or automation. Those are
PB-DIST-1+ and are explicitly **not** authorized by this ADR.

The permanent guard `tests/Feature/PageBuilder/PageBuilderDistributionArchitectureGuardTest`
encodes the invariants below. Guard green + zero runtime regression = freeze accepted.

> **Path note.** The three canonical distribution paths named in the PB-DIST-0 brief map to
> the repository's existing conventions: the ADR and freeze document live in
> `plugins/page-builder/docs/` beside the six accepted foundation freeze docs (so they travel
> *with* the plugin), and the guard lives in `tests/Feature/PageBuilder/` beside the five
> existing architecture guards. A literal `tests/Architecture/` path is **gitignored**
> (`.gitignore` `tests/*` un-ignores only `tests/Feature/{PageBuilder,Localization,Seo}/`) and
> is not in the PHPUnit `Feature`/`Unit` suites, so a guard placed there would be untracked
> and undiscovered. The chosen path is trackable without `git add -f` and runs in the suite.

---

## 2. Context

The Community Page Builder is **developed inside** the TN CMS monorepo but must be
**distributable as a standalone Community plugin**. Distribution must be deterministic and
must *never* accidentally carry Core, Ecommerce, Knowledge Library, Monetization, ACF, other
plugins, unrelated tests/docs, local tooling, AI/Claude/MCP artifacts, caches, build garbage,
worktrees, or snapshots. Only the plugin itself.

Once frozen, this boundary is permanent. Every future exporter/projection/ZIP tool must obey
it; it may never redefine it.

---

## 3. Complete file-category inventory (nothing unclassified)

Authoritative source tree: **`plugins/page-builder/`** (1,410 tracked files at freeze).

| # | Category | Representative paths | Class |
|---|---|---|---|
| 1 | Production PHP | `src/**` (607 `.php`) | SOURCE · release |
| 2 | Runtime config | `config/page-builder.php` | SOURCE · release (mandatory) |
| 3 | Routes | `routes/**` | SOURCE · release (mandatory) |
| 4 | Migrations | `database/**` | SOURCE · release (mandatory) |
| 5 | Plugin manifest | `plugin.json` | SOURCE · release (mandatory) |
| 6 | Vue components | `resources/{editor,frontend-editor,shared-ui,views,patterns}/**` (116 `.vue`) | SOURCE · release |
| 7 | TypeScript | `resources/**/*.ts` (447 `.ts`) | SOURCE · release (compiled) |
| 8 | Stylesheets | `resources/**/*.css` (9), svg (12) | SOURCE · release |
| 9 | Language files | `resources/lang/**` | SOURCE · release |
| 10 | Blade views | `resources/views/**` | SOURCE · release |
| 11 | Build config | `vite.config.ts`, `vite.frontend-shell.config.ts`, `vite.frontend-iframe.config.ts`, `vite.workspace.config.ts`, `vitest.config.ts`, `tsconfig.json` | SOURCE (repo) · **not** release runtime |
| 12 | JS toolchain deps | `package.json`, `package-lock.json` | SOURCE (repo) · **not** release runtime |
| 13 | Docs / ADRs | `docs/**` (179 `.md`, incl. this ADR + freeze) | SOURCE (repo) · **not** release runtime |
| 14 | Architecture note | `architecture/design-resource-platform.md` | SOURCE (repo) · **not** release runtime |
| 15 | Tests + fixtures | `tests/Fixtures/*.json` (and the PB suites under repo `tests/Feature/PageBuilder/`) | DEVELOPER-ONLY · never release |
| 16 | Plugin gitignore | `.gitignore` (`/node_modules`) | SOURCE (repo) · not release |
| 17 | Dev scratch | `_lf.md` | DEVELOPER-ONLY · never release |
| 18 | Generated bundles | `public/vendor/page-builder/**` | GENERATED · ignored · rebuilt · **release-only artifact** |
| 19 | JS deps tree | `plugins/page-builder/node_modules/**` | EXTERNAL · ignored · never commit/export |
| 20 | Diagnostic harnesses | `docs/_lt*_verify.php` (6, untracked) | DEVELOPER-ONLY · retained untracked · never release |
| 21 | Repo tooling / AI / MCP | `.claude/**`, `.tokensave/**`, `.idea`, `.vscode`, `.headroom*`, worktrees, `wip/*` snapshots | FORBIDDEN · never commit under plugin · never export |
| 22 | Framework vendor | repo `vendor/**` | EXTERNAL · ignored · never export |
| 23 | Runtime state | repo `storage/**`, logs, caches, temp, coverage | FORBIDDEN · never export |

No plugin file category remains unclassified.

---

## 4. Permanent source authority

There is **exactly one** authoritative source tree: `plugins/page-builder/`, identified by
`plugin.json` with `slug = "page-builder"`. Invariants:

- Exactly one `plugin.json` in the repo declaring `slug = page-builder`.
- No second "page-builder" plugin root may exist.
- Everything outside `plugins/page-builder/` is, for distribution purposes, one of:
  **Generated** (rebuilt bundles), **External** (vendor / node_modules / framework),
  **Runtime** (host CMS wiring), **Developer-only** (tooling/tests/docs harnesses), or
  **Forbidden** (Core/other plugins/AI/MCP/caches/snapshots). None of these are ever exported.

Why: a single source root makes projection deterministic and prevents Core/plugin bleed.

---

## 5. Distribution manifest (frozen)

Two distinct manifests are frozen. Ambiguity is not permitted.

### 5.1 Source-repo projection (GitHub standalone) — distributable folders/files

**Mandatory:** `src/`, `resources/`, `routes/`, `database/`, `config/`, `plugin.json`.
**Required for build:** `package.json`, `package-lock.json`, `tsconfig.json`,
`vite.config.ts`, `vite.frontend-shell.config.ts`, `vite.frontend-iframe.config.ts`,
`vite.workspace.config.ts`, `vitest.config.ts`, `.gitignore`.
**Optional:** `docs/` (excluding untracked `_lt*_verify.php`), `architecture/`, `tests/Fixtures/`.
**Distributable file types (source repo):** `.php .ts .vue .css .svg .json .md .blade.php`
plus the manifest/build config listed above.

### 5.2 Release runtime (ZIP) — distributable folders/files

**Mandatory:** `src/`, `resources/` (source assets), `routes/`, `database/`, `config/`,
`plugin.json`, and freshly built `public/vendor/page-builder/**` (see §6).
**Forbidden in release ZIP:** `tests/`, `docs/`, `architecture/`, `_lf.md`, all `vite*/vitest/tsconfig`
build config, `package.json`, `package-lock.json`, `node_modules/`, `.gitignore`, any dev/AI/MCP artifact.

### 5.3 Forbidden in every manifest (source *and* release)

`node_modules/`, repo `vendor/`, `public/vendor/page-builder/` **as source control**,
`.claude/`, `.tokensave/`, `.idea`, `.vscode`, `.headroom*`, `coverage/`, `storage/`, logs,
temp, caches, worktrees, `wip/*` snapshots, and **any** path under `packages/thenguyen/`,
`plugins/ecommerce/`, `plugins/advanced-custom-field/`, `plugins/knowledge-library/`,
`plugins/monetization/`, `themes/`, or unrelated repo `docs/`.

---

## 6. Generated-assets policy (frozen)

`public/vendor/page-builder/**`:

- **Source-controlled?** No. It is gitignored (`.gitignore` `/public/vendor/page-builder/`).
- **Generated?** Yes, by `npm run build` (four vite configs: editor, frontend-shell,
  frontend-iframe, workspace).
- **Rebuilt?** Deterministically from `resources/**` source. Reproducibility proven at
  PB-CE-FREEZE-2R (4/4 builds from committed source).
- **Ignored?** Yes, never committed.
- **Release artifact?** Yes — the release ZIP **must** contain freshly built bundles;
  it must never contain the build toolchain that produced them.

Rule: bundles are *never* a source-control input and *always* a release output.

---

## 7. Build boundary (frozen)

| Output | In Git? | In Release ZIP? | Reproducible? |
|---|---|---|---|
| `resources/**` source | Yes | Yes | n/a (authoritative) |
| `public/vendor/page-builder/**` bundles | **No** (ignored) | **Yes** (built) | Yes (`npm run build`) |
| `node_modules/**` | No (ignored) | No | Yes (`npm ci`) |
| build/vite/vitest/tsconfig config | Yes (repo) | **No** | n/a |
| coverage / caches / temp | No | No | n/a |

---

## 8. Developer-only boundary (frozen)

The following are developer tooling and must **never** leak into any release and must never
be committed *inside* `plugins/page-builder/`:

Claude/`.claude` artifacts · Cursor artifacts · MCP files · Headroom (`.headroom*`) ·
`.tokensave` · `.idea` · `.vscode` · `coverage` · temp/debug · `docs/_lt*_verify.php`
diagnostic harnesses (retained untracked) · repo-level session/checkpoint files · worktrees.

Tests (`tests/Fixtures/` inside the plugin and the PB suites under repo
`tests/Feature/PageBuilder/`) are developer-only for **release** purposes but are part of the
source-repo projection.

---

## 9. Git boundary (frozen)

- **Tracked (source):** the 1,410 plugin files in §3 rows 1–17.
- **Ignored:** `node_modules/`, `public/vendor/page-builder/`, repo `vendor/`, `.idea`,
  `.vscode`, `storage/*`, coverage, `.tokensave/`.
- **Generated:** `public/vendor/page-builder/**` (rebuilt, never committed).
- **Release-only:** built bundles (produced at package time, not in Git).
- **Never-commit / never-export:** AI/MCP/Claude/Headroom artifacts, caches, snapshots,
  worktrees, Core, other plugins.

---

## 10. Standalone compatibility (architecture only)

The plugin is capable of independent life. Dependency classes:

| Class | Examples | Notes |
|---|---|---|
| **Plugin internal** | `Plugins\PageBuilder\**` (src), `resources/**` | Wholly owned; travels with plugin. |
| **CMS contract** | `cms.content.body`, `cms.form.schema.*`, `cms.language`, `preview_url`, PublicLocale/LanguageConfiguration contracts, permission seam | Consumed only through public seams (locked by the foundation ADRs). Standalone builds must provide these contracts or a shim. |
| **Laravel** | Illuminate framework, Eloquent, routing, Blade | Host-provided. |
| **Filament** | Admin panel integration via `plugin.json` `filament` | Host-provided; optional at pure-runtime. |
| **Optional** | theme override hooks, render hooks | Degrade gracefully when absent. |
| **Forbidden** | Core internals (`packages/thenguyen/**` concrete classes), Ecommerce/ACF/KL/Monetization | Must never be imported or exported. |
| **Future Cloud** | sync/collab/revision-server/AI | Out of Community scope (§11). Not a dependency. |

The plugin ships **no `composer.json`** today (it is a TN CMS `plugin.json` manifest plugin).
A standalone GitHub projection will need a generated `composer.json` declaring the CMS-contract
and Laravel/Filament requirements — **that is PB-DIST-1 work, not authorized here.**

---

## 11. Community boundary (frozen)

**Community INCLUDES:** single-node · single-user editing · whole-layout publish ·
translation · preview · theme override · registries · hooks · render runtime.

**Community DOES NOT INCLUDE:** Cloud · Sync · Revision Server · Collaboration ·
live multi-user · AI services · Marketplace · remote storage.

This boundary is permanent for the Community distribution.

---

## 12. Release ZIP policy (frozen — no ZIP created here)

- **Enters ZIP:** `src/`, `resources/`, `routes/`, `database/`, `config/`, `plugin.json`,
  freshly built `public/vendor/page-builder/**`.
- **Never enters ZIP:** tests, docs, architecture notes, `_lf.md`, build/vite/tsconfig config,
  `package*.json`, `node_modules/`, `.gitignore`, dev/AI/MCP artifacts, Core, other plugins.
- **Directory structure:** ZIP root = plugin root (`page-builder/…`), mirroring §5.2.
- **Version metadata:** authoritative from `plugin.json` `version`.
- **Checksums / signing / GitHub Release / Marketplace:** future; specified here only as
  boundary reservations, not implemented.

---

## 13. GitHub projection (architecture only)

The monorepo projects into a standalone repository by copying **only** the §5.1 manifest from
the single source root `plugins/page-builder/`, rewriting nothing in the monorepo, and adding
(at PB-DIST-1) a generated standalone `composer.json` + README/LICENSE. No workflow, script,
or automation is defined or created by this ADR.

---

## 14. Forbidden patterns (permanent rejection)

Exporting: Core · repo `vendor/` · `node_modules/` · build cache · Claude files · MCP files ·
Headroom files · worktrees · `wip/*` snapshots · unrelated plugins · unrelated docs ·
unintended tests · runtime cache · `storage/` · logs · temp.

Any tool that would include any of the above is non-compliant and must fail the guard.

---

## 15. Runtime changes

**None.** PB-DIST-0 is documentation + one guard test. No production code is modified. A
production change would be permitted only if this audit had proven an architectural defect; it
did not.

---

## 16. Permanent architecture guard

`PageBuilderDistributionArchitectureGuardTest` verifies:

1. Exactly one plugin root (`plugin.json` slug `page-builder`).
2. No forbidden dev/generated/external path is tracked inside the plugin.
3. Generated bundles (`public/vendor/page-builder/`) are ignored, not tracked.
4. No Core / other-plugin path is part of the distribution manifest.
5. Every tracked plugin top-level entry is in the frozen distribution allowlist (no
   unclassified category).
6. Mandatory release members exist (`src`, `resources`, `routes`, `database`,
   `config`, `plugin.json`).
7. Developer-only artifacts (node_modules, tests, `.tokensave`, `.idea`, `.vscode`,
   coverage) are forbidden from the tracked plugin set.
8. This ADR + the freeze document exist (architecture is source-controlled).

---

## 17. Acceptance

Frozen when: distribution architecture, release boundary, source authority, generated-asset
policy, developer-artifact policy, and Community boundary are all locked above; the guard is
green; zero runtime regression; zero Core change; zero exporter/workflow/ZIP. PB-DIST-1 is
**not** started.
