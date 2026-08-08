# LT-2 — Architecture Regression

**Status: VERDICT — PASS. No frozen LT-1 contract changed.**

Proves LT-2 satisfied the exit criteria: it added persistence **without** modifying any frozen LT-1
contract.

## Frozen contracts — unchanged (verified)

Zero `M` (modified) edits to any of these files this phase (git-verified; standalone-verified behaviour):

| Frozen contract | File | Status |
|---|---|---|
| Descriptor | `LayoutTranslationDescriptor.php` | unchanged |
| Identity | `Identity/TranslationGroupKey`, `PageTranslationKey`, `LayoutKey` | unchanged |
| Relationship model | Group → Page Translation → Layout | unchanged |
| Capability model | `LayoutTranslationCapabilities`, `…CapabilityResolverInterface` (`capabilitiesFor()` still 0-arg) | unchanged |
| State model | `State/*` (`LayoutTranslationStatus` + 3 dimension enums) | unchanged |
| Resolution fault | `LayoutTranslationResolutionFault` | unchanged |
| Resolver | `LayoutTranslationResolver` (output contract) | unchanged |
| Validator responsibility | `LayoutRelationshipValidator` | unchanged |
| Diagnostics contract | `LayoutTranslationDiagnostics` | unchanged |
| State-reader seam | `Contracts/LayoutTranslationStateReaderInterface` | unchanged |

No `fromArray()` / `fromDimensions()` factory was added to any frozen value object; rehydration uses the
existing factories and branch-on-existence. No Core file was modified.

## What LT-2 changed (additive only)

- **New:** `LayoutTranslation/Persistence/**` (contracts, results, model, repository, adapters,
  reconciler, validator, console command); the binding migration.
- **Edited (wiring, not a frozen contract):** `PageBuilderServiceProvider.php` — additive DI bindings
  and a flag-aware state-reader selection; registered the backfill command. `config/page-builder.php`
  unchanged this phase.

## Regression guard test

`tests/Feature/PageBuilder/PageBuilderLayoutTranslationBindingTest::test_lt1_frozen_contracts_are_unchanged`
asserts, at runtime: capability resolution is still status-free (0-arg), the resolver still returns a
descriptor with the frozen multi-dimensional API (`existence()`, `fault()`), and the frozen validator
still validates a descriptor. The full LT-1 suite
(`PageBuilderLayoutTranslationTest`) is unchanged and remains the LT-1 contract guard.

## Verdict

**PASS** — LT-2 is additive and behind the frozen boundary. No frozen LT-1 contract, identity semantics,
relationship model, state dimensions, capability contract, validator responsibility, or diagnostics
contract was changed. LT-2 does not require a new ADR.
