# LT-3 — Final Governance (PERMANENT LOCK)

**Status: RATIFIED & FROZEN.** With this document LT-3 is permanently frozen. It ratifies three
permanent invariants that later phases (LT-4 baseline onward) must uphold. Governance only — no code,
schema, or service change accompanies it.

Complements [`LT-3-BINDING-LIFECYCLE-GOVERNANCE.md`](./LT-3-BINDING-LIFECYCLE-GOVERNANCE.md),
[`LT-3-TRANSACTION-BOUNDARY-GOVERNANCE.md`](./LT-3-TRANSACTION-BOUNDARY-GOVERNANCE.md),
[`LT-3-CLONE-ARCHITECTURE.md`](./LT-3-CLONE-ARCHITECTURE.md),
[`LT-3-CLONE-FAILURE-MODEL.md`](./LT-3-CLONE-FAILURE-MODEL.md), and
[`LT-3-ARCHITECTURE-REGRESSION.md`](./LT-3-ARCHITECTURE-REGRESSION.md).

---

## Invariant 1 — Clone provenance is intentionally absent in LT-3

LT-3 records **no provenance**: the clone does not persist where the target came from, a source
snapshot, a baseline, or any source→target tracking relationship. The initial
`Synchronization = IN_SYNC` status is a point-in-time creation marker only — it means "created from the
source as read at clone time"; it is **not** provenance and does **not** track future source changes.

**Baseline (LT-4) becomes the first authoritative provenance model.** No later phase may retroactively
reinterpret an LT-3 clone's status as provenance; provenance is established only when LT-4 records an
explicit, authoritative baseline. Until then, a cloned target is simply an independent draft with no
tracked lineage.

## Invariant 2 — Clone always creates an independent payload graph

The target document is an **independent payload graph**. The clone copies the selected source payload
by value; **no shared mutable payload references** exist between source and target. Mutating the source
after a successful clone can never affect the target, and vice versa. (In the implementation, payloads
are plain array value types copied verbatim into a distinct `pb_layouts` row — no object aliasing, no
shared references.) Any future implementation change that would introduce shared/aliased payload state
between source and target is forbidden and requires a new ADR.

## Invariant 3 — Clone capability advertises structural support only

`CloneCapability` (the advertised `supportsClone`) answers **only** "does this implementation
structurally support cloning?". It never answers:

- **eligibility** — is this specific source/target clonable right now?
- **authorization** — may this actor perform it?
- **runtime workflow state** — should this workflow run, and what happened?

Capability, Eligibility, Authorization, and Workflow remain **permanently separated** layers.
Capability is implementation-static (status-free); the eligibility evaluator, the caller's
authorization, and the clone service/result own the other three respectively. No later phase may
collapse these layers.

---

## Freeze

LT-3 is **permanently frozen**. The clone contracts, the generic Layout Owner abstraction (Option D),
the eligibility/capability boundary, the binding-lifecycle and transaction-boundary governance, and
these three invariants may be extended only additively; any change to their meaning requires a new ADR.
