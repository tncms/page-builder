# LT-5 — Structural Diff Engine Architecture

**Status: IMPLEMENTED (LT-5). Read-only, deterministic, payload-safe. No schema, no writes, no frozen
contract change.**

A read-only three-way structural diff (per frozen LT-0-ADR-001 D8), built on LT-4 correspondence. It
computes what changed; it decides nothing — that is the synchronization plan (LT-6). Gate satisfied:
diff is read-only, deterministic, and payload-safe.

---

## Model

`NodeChangeType` (`src/LayoutTranslation/Diff/`) — the canonical change classes: `Added`, `Removed`,
`Moved`, `StructureUpdated`, `StyleUpdated`, `SettingUpdated`, `ContentUpdated`, `Unchanged`,
`Conflicted`. LT-5 emits the structurally-determinable classes; **`StyleUpdated`/`SettingUpdated` are
reserved** (distinguishing them from content requires a field taxonomy — an additive future refinement).

- `NodeChange` — `{origin, type}` only (no payload → payload-safe).
- `TwoWayDiff` — sorted, non-`Unchanged` changes from a `from` to a `to` layout.
- `ThreeWayStructuralDiff` — `sourceDiff` (B→S), `targetDiff` (B→T), and `conflicts` (origins changed on
  both sides).

## Engine

`StructuralDiffEngine` (stateless):

- `compare(from, to)` — index both layouts by **correspondence origin** (LT-4 `NodeCorrespondence`),
  then per node: `Added`/`Removed` for unmatched; for matched, `Moved` (parent or position changed) →
  `StructureUpdated` (child origin set/order changed) → `ContentUpdated` (own data hash changed, ids +
  correspondence excluded) → else `Unchanged` (omitted).
- `threeWay(baseline, source, target)` — `compare(B,S)` + `compare(B,T)`; `conflicts` = origins changed
  in both.

**Matching is by origin, not physical id** — a node whose physical id was regenerated still matches its
counterpart (verified). Content hashing strips physical ids and `data['_origin']`, so correspondence and
identity churn never register as content changes.

## Guarantees

- **Read-only** — no mutation, persistence, clone, or sync; pure functions of the input payloads.
- **Deterministic** — output sorted by origin; identical inputs → byte-identical output (verified).
- **Payload-safe** — the diff exposes only origins + change types; no node content is copied or leaked
  (verified — content strings never appear in the diff output).

## Non-goals

No plan/policy/conflict-resolution (LT-6), no execution/merge (LT-7), no UI (LT-8), no persistence, no
Style/Setting field taxonomy (reserved), no Core/frozen-contract change.

## Verification

11/11 standalone assertions: identical→empty; content/added/removed/moved/structure classification;
origin-match despite id regeneration; three-way conflict + disjoint-no-conflict; determinism;
payload-safety. `php -l` clean.
