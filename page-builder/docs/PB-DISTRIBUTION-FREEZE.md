# PB-DISTRIBUTION-FREEZE — Page Builder Standalone Distribution Boundary

**Status:** Accepted — Distribution Boundary Frozen (architecture only; no exporter / workflow / ZIP)
**Date:** 2026-08-06
**Phase:** PB-DIST-0
**Authority:** `ADR-PB-DISTRIBUTION-ARCHITECTURE.md`
**Guard:** `tests/Feature/PageBuilder/PageBuilderDistributionArchitectureGuardTest.php`
**Scope:** Community Edition standalone plugin distribution boundary only. No exporter, GitHub
workflow, release ZIP, Cloud, or automation. No production runtime / Core / Ecommerce / Theme change.

---

## 1. Freeze status

The distribution boundary for the standalone Community Page Builder is **frozen**. This
document + the ADR + the guard permanently lock *what may leave the monorepo as the plugin*
and *what must never leave it*. No tooling is written; PB-DIST-1 is not started.

Verdict: **A — distribution architecture frozen; zero runtime/Core change; guard green.**

---

## 2. Frozen decisions (summary matrix)

| Boundary | Frozen decision |
|---|---|
| Source authority | Single root `plugins/page-builder/` (`plugin.json` slug `page-builder`). |
| Distribution manifest | Two frozen manifests: source-repo projection (§5.1 ADR) + release ZIP (§5.2 ADR). |
| Generated assets | `public/vendor/page-builder/**` = generated · ignored · rebuilt · release-only. Never source-controlled. |
| Build boundary | Bundles never in Git, always in ZIP (built). Toolchain never in ZIP. |
| Developer-only | Claude/MCP/Headroom/`.tokensave`/`.idea`/`.vscode`/coverage/`_lt*_verify.php`/tests → never release, never committed inside plugin. |
| Git boundary | Tracked source vs ignored (node_modules, bundles, vendor, storage) vs release-only (built bundles) vs never-commit (AI/MCP/snapshots). |
| Standalone deps | Plugin-internal / CMS-contract / Laravel / Filament / optional / forbidden(Core+plugins) / future-Cloud. |
| Community boundary | INCLUDES single-node editing, whole-layout publish, translation, preview, theme override, registries, hooks, render runtime. EXCLUDES Cloud/Sync/Revision Server/Collaboration/multi-user/AI/Marketplace/remote storage. |
| Release ZIP | Reservation only — structure, `plugin.json` version metadata, future checksums/signing/Release/Marketplace. No ZIP created. |
| GitHub projection | Copy §5.1 manifest from single root; add generated `composer.json`+README/LICENSE at PB-DIST-1. No workflow/script here. |

---

## 3. Forbidden export set (permanent)

Core (`packages/thenguyen/**`) · Ecommerce · ACF · Knowledge Library · Monetization · other
plugins · themes · unrelated docs/tests · repo `vendor/` · `node_modules/` · build cache ·
`.claude` / MCP / Headroom / `.tokensave` · `.idea` / `.vscode` · `storage/` · logs · temp ·
coverage · worktrees · `wip/*` snapshots.

---

## 4. What this freeze does NOT do

No exporter. No GitHub Actions/workflow. No release ZIP. No `composer.json` generation. No
push. No Core/Ecommerce/Theme/runtime modification. No PB-DIST-1.

---

## 5. Guard coverage

`PageBuilderDistributionArchitectureGuardTest` locks: single plugin root · no forbidden path
tracked in plugin · generated bundles ignored · no Core/other-plugin in manifest · deterministic
top-level allowlist (no unclassified category) · mandatory release members present · developer
artifacts forbidden · this freeze + ADR present.

---

## 6. Lineage

Builds on PB-CE-FREEZE (Community Foundation Frozen, commits `371e3a6` runtime + `7651e3a`
foundation). This is the next boundary above the foundation and below any tooling.

**Next (user-gated):** PB-DIST-1 — exporter/projection implementation. Do **not** start without
explicit approval.
