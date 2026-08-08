# LT-6 — Synchronization Plan & Conflict Policy Architecture

**Status: IMPLEMENTED (LT-6). Read-only, deterministic, fail-closed. No schema, no writes, no frozen
contract change.**

Turns the LT-5 three-way structural diff into an explicit, **reviewable** synchronization plan (per
frozen LT-0-ADR-001 D9/D10). It decides *what would happen*; it never mutates the target — execution is
LT-7. Gate satisfied: no target mutation before explicit execution.

---

## Model (`src/LayoutTranslation/Plan/`)

- `SynchronizationClassification` — `Safe`, `ReviewRequired`, `Conflict`, `Unsupported` (reserved).
- `SynchronizationDecision` (default) — `AcceptSource`, `KeepTarget`, `Ignore`.
- `ConflictReason` — `TranslatedContentDiverged`, `SourceRemovedTargetModified`, `StructuralConflict`,
  `MissingCorrespondence` (reserved).
- `SynchronizationPlanItem` — `{origin, sourceChange, classification, defaultDecision, conflictReason}`
  (payload-safe: origins + enums only).
- `SynchronizationPlan` — items + `summary()` (safe / review / conflict counts), `requiresReview()`,
  `safeItems()`.

## Policy (`SynchronizationPlanner`, stateless)

For each **source** change (B→S), classified against the target's customizations (B→T):

| Situation | Classification | Default decision | Reason |
|---|---|---|---|
| Target did not touch the node | **Safe** | `AcceptSource` | — |
| Both changed the node's content | **ReviewRequired** | `KeepTarget` | `TranslatedContentDiverged` |
| Source removed a node the target modified | **Conflict** | `KeepTarget` | `SourceRemovedTargetModified` |
| Any other both-sides change | **Conflict** | `KeepTarget` | `StructuralConflict` |

**Fail-closed (D9 non-destructive invariants):** a node changed on both sides **never** defaults to
`AcceptSource` — translated content is never auto-overwritten, and a target-modified node is never
auto-removed. Target-only customizations are preserved (they never appear in the plan). The default
decisions are just that — **defaults**; selective acceptance and execution are LT-7.

## Guarantees

- **Read-only** — a pure function of the diff; no mutation, persistence, or execution.
- **Deterministic** — plan order follows the (sorted) source diff; identical inputs → identical plan
  (verified).
- **Payload-safe** — origins + enums only; no node content in the plan (verified with unique content
  tokens).

## Non-goals

No execution/merge/rollback (LT-7), no UI (LT-8), no persistence, no auto-resolution of conflicts, no
Style/Setting taxonomy, no Core/frozen-contract change.

## Verification

9/9 standalone: safe→AcceptSource; content-conflict→ReviewRequired/KeepTarget/TranslatedContentDiverged;
removed-vs-modified→Conflict/SourceRemovedTargetModified; structural→Conflict/StructuralConflict;
fail-closed (no non-safe AcceptSource); summary/requiresReview; empty plan on no source change;
determinism; payload-safety. `php -l` clean.
