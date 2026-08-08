# LT-4 — Architecture Regression

**Status: VERDICT — PASS.** LT-4 added baseline + correspondence without modifying any frozen
LT-1/LT-2/LT-3 contract, and without a Core change.

## Frozen contracts — unchanged (git-verified: zero `M` edits this phase)

| Frozen | Status |
|---|---|
| LT-1 descriptor / identity / state / capability / resolver / validator / diagnostics | unchanged |
| LT-1.5 capability boundary (`capabilitiesFor()` 0-arg, status-free) | unchanged |
| LT-2 binding table, model, repository, result VOs (`Persistence/*`) | unchanged |
| LT-3 clone engine, Option-D owner abstraction, governance | unchanged |
| `pb_layouts` schema | unchanged (no locale column) |

## What LT-4 added (additive only)

- **New:** `src/LayoutTranslation/Correspondence/*` (node correspondence engine) and
  `src/LayoutTranslation/Baseline/*` (fingerprint, VO, contract, model, repository, capture service +
  internal exception, diagnostics); a **separate** additive migration
  `2026_07_25_000001_create_pb_layout_translation_baselines_table.php` (created, not executed).
- **Edited (wiring only):** `PageBuilderServiceProvider.php` — additive DI bindings for the baseline
  repository, capture service, and diagnostics.
- **No** LT-2 binding-table change (baseline is a **separate** table — decision B2, chosen specifically
  to keep LT-2 frozen), **no** `pb_layouts` change, **no** Core file change, **no** external API/HTTP.

## Correspondence & source safety

Correspondence is stamped on the **target only** (`data['_origin']`); the source document is never
mutated (LT-3 §3.7 upheld). The baseline table has **no FK** to `pb_layouts`, so the source remains
independently deletable/editable.

## Verdict

**PASS** — LT-4 is additive and behind the flag. No frozen LT-1/LT-2/LT-3 contract, identity, state,
capability, validator, diagnostics, or schema was changed. LT-4 requires no new ADR. The two consequential
decisions (A1 correspondence, B2 separate baseline store) are documented in
[`LT-4-BASELINE-ARCHITECTURE.md`](./LT-4-BASELINE-ARCHITECTURE.md); B2 was chosen over the audit's B1
precisely to keep LT-2 frozen.
