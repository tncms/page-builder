# LT-3 — Architecture Regression

**Status: VERDICT — PASS.** LT-3 added the clone engine (Option D generic Layout Owner) without
modifying any frozen LT-1 or LT-2 contract, and without a Core change.

## Frozen contracts — unchanged (git-verified: zero `M` edits this phase)

| Frozen | File(s) | Status |
|---|---|---|
| LT-1 Descriptor | `LayoutTranslationDescriptor.php` | unchanged |
| LT-1 Identity | `Identity/*` | unchanged |
| LT-1 State model | `State/*` | unchanged |
| LT-1 Capability contract | `Contracts/LayoutTranslationCapabilityResolverInterface.php` (still 0-arg) | unchanged |
| LT-1 default capability resolver | `DefaultLayoutTranslationCapabilityResolver.php` (still all-false) | unchanged |
| LT-1 Resolver / Validator / Diagnostics | `LayoutTranslationResolver/…Validator/…Diagnostics.php` | unchanged |
| LT-2 binding schema | `2026_07_20_000001_create_pb_layout_translation_bindings_table.php` | unchanged |
| LT-2 persistence contracts | `Persistence/Contracts/*`, `Persistence/Results/*` | unchanged |
| `pb_layouts` schema | `2026_07_06_000001_create_pb_layouts_table.php` | unchanged (no locale column) |

## Capability evolution (contract-preserving)

LT-3 advertises the `clone` capability via a **new** `CloneCapableCapabilityResolver` and an additive
container rebind — the frozen `DefaultLayoutTranslationCapabilityResolver` is untouched, and
`capabilitiesFor()` remains status-free and 0-arg (the LT-1.5 Capability/Eligibility boundary holds).
This is the "richer static set as each operation lands" the LT-1 ADR §4 anticipated — an additive
implementation change, not a contract change.

## What LT-3 added (additive only)

- **New:** `src/Ownership/*` (generic, translation-agnostic Layout Owner abstraction); the whole
  `src/LayoutTranslation/Cloning/*` subtree (command, results, eligibility, service, adapters,
  capability, owner + `TargetOwnerResolverInterface`, console command).
- **Edited (wiring only):** `PageBuilderServiceProvider.php` — additive DI bindings, the clone
  capability rebind, owner-type registration, and the clone console command.
- **No** new schema, **no** migration, **no** `pb_layouts`/LT-2 change, **no** Core file change, **no**
  external API/HTTP.

## Runtime regression guard

`PageBuilderLayoutTranslationCloneTest::test_lt1_and_lt2_contracts_are_unchanged` asserts at runtime:
the default resolver is still all-false + 0-arg, `pb_layouts` still has no `locale` column, and the LT-2
binding repository still returns domain contracts. The existing LT-1 and LT-2 suites remain unchanged
and continue to guard their contracts.

## Verdict

**PASS** — LT-3 is additive and behind the flag. No frozen LT-1/LT-2 contract, identity semantics,
relationship model, state dimensions, capability contract, validator responsibility, diagnostics
contract, or LT-2 schema/repository contract was changed. LT-3 requires no new ADR.
