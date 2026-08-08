# LT-8 — Presentation Governance (LOCK)

**Status: RATIFIED & LOCKED (before LT-8C). Governance only — no code, no schema, no migration.** Ratifies
the two permanent invariants that govern the Layout Translation **Review Workspace presentation layer**
(Filament / Livewire / Blade / Vue). They sit above the LT-8C implementation and every later UI phase, and
compose with — never weaken — the frozen LT-8A architecture (INV-LT-8A-01…08), the frozen LT-8B Workspace
Runtime, and the frozen LT-7 pipeline.

The presentation layer is the outermost ring. It renders the runtime and forwards user intent to it — and
does nothing else. These two invariants make that boundary permanent.

---

## INV-LT-8-PRES-01 — The presentation layer owns no domain state

**The presentation layer owns no domain state.** Filament, Livewire, Blade, and Vue may hold **only**
ephemeral visual state, such as:

- the selected node,
- expanded / collapsed groups,
- the active panel,
- filters,
- visual preferences.

They may **not** become the source of truth for:

- the `ReviewSession` lifecycle,
- the `DecisionSet`,
- provenance,
- acceptance,
- execution state.

**All domain transitions must go through the frozen LT-8B Workspace Runtime.**

- The authoritative carrier of a session's decisions is the runtime's `ReviewSessionStoreInterface`
  (payload-safe snapshot), reconstructed each request via the runtime (INV-LT-8A-07). The UI holds a
  session *reference* (id) plus its ephemeral visual state — never the decisions themselves as its own
  round-tripped truth.
- Every state-changing user action is applied by calling a Workspace Runtime operation
  (`ReviewSession::decide` / `resetDecision` / `select…` / `pause` / `submit`) — the UI never advances the
  lifecycle, mutates the `DecisionSet`, recomputes provenance, or tracks execution state on its own.
- Ephemeral visual state may be lost (page reload, new tab) with no loss of domain truth: the session is
  reconstructed from `(SynchronizationPlan, DecisionSet)` (INV-LT-8A-07). If the UI holds it, it is
  disposable by definition.

**Rationale.** A UI that owns no domain state cannot diverge from the runtime, cannot silently persist a
decision the runtime never saw, and cannot resurrect stale execution state. It keeps the runtime the
single authority and makes the whole presentation layer safe to rebuild, re-render, or replace.

## INV-LT-8-PRES-02 — Each UI action maps one-to-one to a Workspace Runtime operation

**Each UI action maps one-to-one to a Workspace Runtime operation.** The UI must **never**:

- duplicate decision logic,
- mutate projections,
- construct acceptance independently,
- assemble execution commands independently,
- call the coordinator directly.

- A click, keypress, or form change resolves to exactly one runtime call. The UI translates input into a
  runtime operation and renders the result; it interprets no policy of its own.
- `SynchronizationAcceptanceSet` is produced **only** by `DecisionSet::toAcceptance()` inside the runtime;
  `ExecuteSynchronizationCommand` is assembled **only** by `ReviewApprovalService`; the coordinator is
  invoked **only** through that service. The presentation layer references none of these types directly.
- Projections (`ReviewTree`, `ReviewGroup`, `ReviewNode`, `PreviewFragment`) are rendered read-only
  (INV-LT-8A-08). The UI never mutates a projection; it re-requests a fresh projection after a runtime
  operation.
- **Preview is single-path.** Preview content is obtained **only** through `PreviewProjectionInterface`
  (via the runtime's preview binding). When a fragment is unavailable, the UI renders a fail-soft
  unavailable state. The UI introduces **no** alternate content-loading path.

**Rationale.** One-to-one mapping guarantees the UI cannot become a second, subtly-different implementation
of the pipeline. Decision policy, acceptance assembly, command construction, and execution stay in exactly
one place (the frozen runtime + pipeline), so the UI can never drift from — or weaken — them.

---

## Scope & precedence

- These invariants bind LT-8C (Review Workspace Presentation) and every later UI phase (including a future
  Vue surface). An implementation may add ephemeral visual affordances; it may **never** hold domain state,
  duplicate decision/acceptance/command logic, mutate a projection, add an alternate preview path, or call
  the coordinator directly.
- They compose with, and never override, INV-LT-8A-01…08, the frozen LT-8B runtime, the frozen LT-7
  pipeline (LT-7-FINAL-GOVERNANCE), and the frozen LT-0-ADR-001 invariants. Where any document appears to
  conflict, the frozen ADRs and these locks prevail.
- Amendment path: a future ADR only. No implementation phase, test, or refactor may weaken them.

## Freeze

With this governance ratified, the presentation boundary is fixed. **LT-8C implements strictly under
INV-LT-8-PRES-01/02 and the frozen LT-8A/LT-8B authority.** Implementation proceeds under them.
