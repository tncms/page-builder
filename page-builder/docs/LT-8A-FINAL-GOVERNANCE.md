# LT-8A — Final Workspace Governance (LOCK)

**Status: RATIFIED & LOCKED (LT-8A). Governance only — no code, no schema, no migration.** Ratifies the
two permanent invariants that, together with **INV-LT-8A-01…06**
([`LT-8A-REVIEW-WORKSPACE-ARCHITECTURE.md`](./LT-8A-REVIEW-WORKSPACE-ARCHITECTURE.md) §1), govern every
current and future Layout Translation **Review Workspace**. With this lock the LT-8A architecture is
**FROZEN**. It may be amended only by a future ADR, never by an implementation phase.

These two invariants are elevated above the design doc: LT-8B (Workspace Runtime) and every later review
surface implement *under* them and may not weaken them.

---

## INV-LT-8A-07 — The Review Workspace is disposable and never a source of truth

**The Review Workspace is disposable. It is never a source of truth. A session must always be fully
reconstructible from exactly two inputs:**

- the **`SynchronizationPlan`** (re-derived deterministically from the frozen LT-5/LT-6 read path), and
- the **`DecisionSet`** (the reviewer's per-origin working decisions).

**No irreplaceable in-memory state may exist.**

- Everything the Workspace shows — the Review Tree, the current selection, navigation position, and every
  Preview fragment — is a **pure projection** of `(SynchronizationPlan, DecisionSet)` (and, for preview,
  of read-only source/target reads). It is recomputed on demand and may be discarded and rebuilt at any
  time with no loss of authoritative state.
- A session snapshot is **payload-safe and reconstruction-sufficient**: it carries origins, decisions,
  `decided` flags, the `PlanProvenance` fingerprints, and (optionally, non-authoritatively) the selected
  origin. It never carries the plan payload, node content, or any preview fragment. Resume re-derives the
  plan, re-verifies provenance, and re-attaches decisions by origin (LT-8A §8.2).
- Corollary: losing the process, the cache, or the whole Workspace instance loses **nothing** that cannot
  be rebuilt from the plan plus the reviewer's decisions. The authoritative record of an *executed* sync
  remains the frozen LT-7 append-only history; the Workspace never becomes a second source of truth for it.

**Rationale.** A disposable Workspace cannot silently diverge from the pipeline, cannot hoard state the
pipeline does not know about, and makes resume, concurrency, and crash-recovery trivially correct: the
only durable truth is the deterministic plan and the reviewer's decisions. This is what keeps the
Workspace *presentation-and-review only* (INV-LT-8A-01) mechanically, not merely by convention.

## INV-LT-8A-08 — Every Review projection is strictly read-only (CQRS separation)

**Every Review projection is read-only. Projection objects never expose:**

- `apply()`
- `save()`
- `accept()`
- `reject()`

**User intent is stored exclusively inside the working `DecisionSet`. This preserves a strict CQRS
separation between projection (query) and decision (command).**

- Projection types — `ReviewTree`, `ReviewGroup`, `ReviewNode`, `PreviewFragment`, `PreviewSide`, and any
  future read model — expose **query methods only**. They carry no mutator, no persistence hook, and no
  decision verb. A `ReviewNode` *reports* its `effectiveDecision`; it cannot *change* it.
- The **only** write surface for reviewer intent is the `DecisionSet`, reached through the command-side
  decision binding. A projection may be handed to any renderer without risk that rendering mutates state.
- The query side (projection generation, selection, navigation, preview binding) and the command side
  (decision binding into the `DecisionSet`) are separate objects with separate responsibilities. Content
  read for preview is query-side and one-directional (INV-LT-8A-02): it never crosses into the command
  side.

**Rationale.** CQRS separation makes the disposable-projection guarantee (INV-LT-8A-07) enforceable: if
projections cannot mutate, they cannot hold authoritative state, so they are always safe to discard and
rebuild. It also keeps the reviewer's intent in exactly one place (`DecisionSet`), which is the single
thing — with the plan — a session reconstructs from.

---

## Scope & precedence

- These invariants bind LT-8B (Workspace Runtime) and every later review/UI phase. An implementation may
  add projections or query surfaces; it may **never** give a projection a mutator, introduce a second
  source of truth, or hold irreplaceable in-memory state.
- They compose with, and never override, **INV-LT-8A-01…06**, the frozen LT-7 pipeline
  (LT-7-FINAL-GOVERNANCE INV-LT-7-EXEC-01/02), and the frozen LT-0-ADR-001 invariants. Where any document
  appears to conflict, this lock and the frozen ADRs prevail.
- Amendment path: a future ADR only. No implementation phase, test, or refactor may weaken them.

## Freeze

With this governance ratified, **LT-8A Architecture is FROZEN.** The LT-8A architecture document and the
eight invariants (INV-LT-8A-01…08) are the authority for LT-8B and beyond. Implementation proceeds under
them.
