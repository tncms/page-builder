# LT-7B2 — Mutation Engine

**Status: IMPLEMENTED & FROZEN (LT-7B2). Pure function `Target + SyncOperation[] → Target′`. No
persistence, no transaction, no baseline, no history, no diagnostics, no service orchestration, no schema,
no migration, no Core change.** The second LT-7B increment: the only component that turns approved
operations into a mutated target document — and nothing more. Governed by the frozen LT-7A architecture,
[`LT-7-FINAL-GOVERNANCE.md`](./LT-7-FINAL-GOVERNANCE.md), and the LT-7B1 contract freeze
([`LT-7B1-EXECUTION-CONTRACT.md`](./LT-7B1-EXECUTION-CONTRACT.md)).

---

## Delivered (`src/LayoutTranslation/Execution/`)

| Component | Kind | Role |
|---|---|---|
| `Contracts/OperationApplierInterface` | interface | The engine boundary: `apply(array $target, SyncOperationSet): array`. Entire input surface is Target + operations (INV-LT-7-OPS-02). |
| `Mutation/DocumentOperationApplier` | engine | The deterministic, side-effect-free applier. Working copy starts as `T`; only named nodes change; everything else passes through verbatim. |
| `Mutation/ApplyFault` | enum | `NodeNotFound` / `ParentNotFound` / `DuplicateOrigin` / `TargetOwnedRemoval` — baseline-free structural faults. |
| `Mutation/OperationApplicationException` | exception | Typed fault + offending origin (an id string, never content). Signals *no* partial document was produced. |

No service, repository, model, migration, transaction, or persistence is introduced.

## Operation semantics (node tree: `sections[] → blocks[] → data.blocks[]`, matched by `_origin`)

| Kind | Effect | Non-destructive guard |
|---|---|---|
| `RemoveNode` | delete the corresponded node | **refuses** if the subtree contains a target-owned (unstamped) node → `TargetOwnedRemoval` (INV-LT-03) |
| `InsertNode` | insert the approved node under a parent origin at a clamped position | **refuses** a duplicate origin → `DuplicateOrigin`; missing parent → `ParentNotFound` |
| `RepositionNode` | move the corresponded node (whole subtree preserved) to a parent/position | missing node/parent → `NodeNotFound`/`ParentNotFound` |
| `ApplyChildSet` | reorder the parent's source-corresponded children into the given order | target-owned / unmentioned children are **preserved** (kept in original relative order, appended after) |
| `ReplaceOwnData` | overwrite the node's own `type`+`data` from source | source `blocks` and `_origin` are stripped; the target's `_origin` and child subtree are **preserved** |

Any unresolvable operation throws — the engine returns `T′` only on complete success (nothing partial).

**Target-owned detection** uses the *stamped* `data['_origin']` only — a physical `id` is not a
correspondence stamp, so `NodeCorrespondence::originOf`'s id-fallback is deliberately not used for the
removal guard (a translator-added node has an id but no stamp, and must be protected).

**Documented boundary (not a gap):** top-level *section* insertion has no parent, so it is not
expressible under the frozen LT-7B1 `InsertNode(parentOrigin …)` contract — such an operation cannot be
constructed. Supporting source-added top-level sections is a future increment (would need an additive
contract), not silently handled here.

---

## Ratified permanent invariants

Elevated to permanent status (amendable only by a future ADR), alongside INV-LT-7-EXEC-01/02 and
INV-LT-7-OPS-01/02.

### INV-LT-7-OPS-03 — a SyncOperation is completely independent

**A `SyncOperation` never contains execution order, priority, dependency, `executeAfter`, or
`executeBefore`. Execution ordering belongs exclusively to `SyncOperationSet`.**

- Confirmed by construction: `SyncOperation`'s only fields are `kind`, `origin`, `parentOrigin`,
  `position`, `nodePayload`, `childOrigins`. None expresses inter-operation order or dependency.
- `position` is a **document placement coordinate** (a node's index among its parent's children), not an
  execution-order field — it describes *where in the tree* a node sits, never *when* an operation runs.
- The engine applies operations strictly in the order `SyncOperationSet::all()` presents them
  (origin-sorted); it never re-orders by kind, priority, or dependency. Ordering authority is the set's,
  never the operation's and never the engine's.

### INV-LT-7-OPS-04 — SyncOperationSet is immutable

**`SyncOperationSet` is immutable. All ordering, normalization, and validation occur during construction.
No mutation API is allowed afterwards.**

- Confirmed by construction: the backing array is `readonly`; the constructor performs dedup
  (duplicate-origin rejection), origin-sort (ordering), and normalization (`array_values`).
- The public surface is read-only — `all()`, `origins()`, `isEmpty()`, `count()`, `toArray()`, and the
  static `empty()`. There is no `add`, `remove`, `merge`, `sort`, or `with*` mutator; a different set is a
  new object.

---

## Architecture regression verification (before B3)

| Obligation | Result |
|---|---|
| Scope = `Target + SyncOperation[] → Target′` only | ✅ single pure class; no persistence/transaction/baseline/history/diagnostics/service |
| INV-LT-02 full-document-replacement prohibited | ✅ working copy starts as `T`; empty op set returns byte-identical `T`; `S` is never a root — the engine has no source input |
| INV-LT-03 target-owned/untouched preserved | ✅ target-owned child survives `ApplyChildSet`; removal of a target-owned-bearing subtree faults; untouched target-owned node byte-identical after a multi-op apply |
| INV-LT-04 content overwrite only where operation says | ✅ `ReplaceOwnData` touches only the named node's own data; siblings/children preserved |
| INV-LT-7-OPS-02 input boundary | ✅ engine reads only Target + operations; no Source/Baseline/Plan parameter exists |
| INV-LT-7-OPS-03 ordering authority | ✅ engine applies in set order; no re-ordering; operations carry no order/priority/dependency |
| Determinism (LT-7-ARCH-REGRESSION §6) | ✅ identical `(Target, ops)` → identical `Target′` |
| Fail-closed, nothing partial | ✅ every unresolvable op throws a typed `ApplyFault`; no partial document returned |
| No Core / frozen-contract change | ✅ Translation→Page Builder only; reuses LT-4 `NodeCorrespondence`; LT-1…LT-6 and LT-7B1 untouched |

**Standalone verification: 20/20 passed** — empty-set identity; `ReplaceOwnData` (leaf + container,
origin/children preservation, no source-blocks leak); `ApplyChildSet` reorder + target-owned
preservation; `InsertNode` into section and into block parent (`data.blocks`); `RemoveNode`;
`RepositionNode` with clamped position; all four faults; determinism; target-owned byte-identity.
`php -l` clean on all engine files. Verified via a temporary standalone harness, removed after passing.

---

## Non-goals (LT-7B2)

No `OperationBuilder` (plan-item + accepted decision + source node → `SyncOperation`) — that lowering is
**LT-7B3**. No provenance guard, eligibility, transaction, `saveDraft`, baseline advance, history store,
diagnostics, service, migration, or Core change. This increment delivers the pure mutation engine only.

**LT-7B2 is frozen. Proceed to LT-7B3 (operation lowering + the transactional execution service) under
INV-LT-7-EXEC-01/02 and INV-LT-7-OPS-01..04.**
