# PB-DIST-1-EXPORT-ARCHITECTURE — Deterministic Plugin Export Tool

**Status:** Accepted (tool implemented; deterministic; no CI/workflow/ZIP publishing)
**Date:** 2026-08-06
**Phase:** PB-DIST-1 (export tool only)
**Authority:** `ADR-PB-DISTRIBUTION-ARCHITECTURE.md` + `PB-DISTRIBUTION-FREEZE.md` (PB-DIST-0, frozen)
**Non-goals:** No GitHub Actions, no release publishing, no repository creation, no push, no runtime/Core/Ecommerce/ADR change, no PB-DIST-2.

> **Path note.** Per the PB-DIST-0 convention, this doc lives in `plugins/page-builder/docs/`
> (with the other freeze docs) and the five tests in `tests/Feature/PageBuilder/` (un-ignored,
> discovered, beside the existing guards). See ADR-PB-DISTRIBUTION-ARCHITECTURE §1 path-note.

---

## 1. Purpose

Implement the deterministic exporter that projects `plugins/page-builder/` from the monorepo
into a standalone distributable plugin repository. Identical source ⇒ byte-identical output.
No hidden logic, no heuristics, no filesystem guessing, no wildcard export. The tool obeys the
PB-DIST-0 architecture exactly and adds nothing to it.

## 2. Deliverables

**`plugins/page-builder/tools/export/`** (namespace `Plugins\PageBuilder\Tools\Export`, loaded by
the self-contained `autoload.php` — never wired into runtime/composer):

| Class | Responsibility |
|---|---|
| `ExportManifest` | Immutable in-memory manifest built from `config/distribution.php`. The one authority. |
| `ManifestValidator` | Fail-closed pre-export validation (templates, mandatory members, forbidden includes). |
| `PathRules` | Single, pure authority for normalize / forbidden / glob / prefix logic. |
| `FileCollector` | Deterministic, sorted collection from the fixed source root only. |
| `ProjectionBuilder` | Source→destination map; only the projection-root prefix is added. |
| `ComposerTemplate` | `composer.json` from one stub + one-source-per-field substitutions. |
| `ReleaseManifest` | `release-manifest.json` with injected (non-runtime) metadata + hashes. |
| `ExportHasher` | Per-file sha256 + aggregate content hash (time/order independent). |
| `ExportReport` | Immutable result DTO. |
| `ExportManager` | Orchestrator: validate → collect → project → hash → generate → write. |
| `ExportException` | Fail-closed failure carrying the exact violated rule. |

**`plugins/page-builder/templates/`**: `composer.json.stub`, `README.stub.md`, `LICENSE.stub`.
**`plugins/page-builder/config/distribution.php`**: the single authoritative manifest (data).
**`tests/Feature/PageBuilder/`**: `PageBuilderDistributionExporterTest`,
`PageBuilderExportDeterminismTest`, `PageBuilderProjectionManifestTest`,
`PageBuilderComposerTemplateTest`, `PageBuilderReleaseManifestTest`.

## 3. Export authority

The exporter reads **only** the fixed `source_root = plugins/page-builder`. It never scans the
repository, never infers plugin roots, never searches for `plugin.json`, never crawls siblings.
`ExportManager` fails closed (`source.authority`) if constructed with any other root.

## 4. Manifest-driven

`config/distribution.php` declares include dirs/files, mandatory members, exclude globs, forbidden
fragments, generated-asset policy, templates, and composer substitutions. Every exported path is
an include entry; every ignored path is an exclude/forbidden entry. No wildcard behaviour, no
hidden exclusion. `FileCollector` walks only include dirs, drops exclude globs, and aborts if any
collected path matches a forbidden fragment.

## 5. Projection

`ProjectionBuilder` maps each collected source path to `page-builder/<same relative path>`,
preserving directory layout, relative paths, namespaces, translations, Blade, Vue, TS, assets,
tests, docs, and `plugin.json`. The **only** transformation is the projection-root prefix — no
path rewriting. Nested legitimate `resources/editor/templates/` is preserved; only the plugin-root
`tools/`, `templates/`, `config/distribution.php`, and `_lf.md` scaffolding are excluded.

## 6. Composer / README / LICENSE

`composer.json` is generated only from `composer.json.stub` + frozen substitutions
(`name`/`type`/`license` = literals; `version`/`description` = `plugin.json`). README and LICENSE
are generated from their stubs + `plugin.json` metadata. No hardcoded values in code, no
AI-generated release notes, one source per field, canonical formatting → deterministic.

## 7. Release manifest

`release-manifest.json` carries `manifest_version`, `export_version`, `plugin_slug`, `version`,
`git_commit` (injected — the exporter never runs git), `git_tag` (reserved), `generated_at`
(injected — never `now()`), `content_hash`, `file_count`, and per-file `files` hashes. It contains
no runtime metadata (no absolute/host paths). Identical injected inputs ⇒ byte-identical manifest.

## 8. Hashing & determinism

Every exported source file is sha256-hashed; the aggregate `content_hash` is sha256 over the
sorted `(destination, hash)` pairs — independent of time, traversal order, and absolute location.
`PageBuilderExportDeterminismTest` exports twice and asserts identical `content_hash` and a
byte-identical projected tree (including the four generated artifacts).

## 9. Validation & error policy

All validation happens before any write; a failure throws `ExportException` with the exact rule
and produces **no** partial output. Checks: mandatory files/folders, required templates, forbidden
includes, duplicate include entries, duplicate destinations, non-empty target. Nothing is silently
skipped.

## 10. Generated assets (PB-DIST-0 §6, unchanged)

The exporter is **filesystem-only**: no npm, no vite, no composer, no artisan. It never builds
assets. Built bundles live at repo `public/vendor/page-builder/` (outside the source root, release-
only) and are therefore **not** part of the PB-DIST-1 source projection. Their inclusion in a
release ZIP is a future phase.

## 11. Guards

The five tests lock: single source authority, single manifest, single projection, single composer
template, single release manifest, deterministic hashes, forbidden-path rejection, and the
generated-asset policy. Combined with the PB-DIST-0 `PageBuilderDistributionArchitectureGuardTest`,
the distribution boundary and its tooling are fully guarded.

## 12. Non-goals / next

No GitHub workflow, release publishing, repository creation, or push. PB-DIST-2 (release ZIP /
GitHub projection automation) is **not** started and requires explicit approval.
