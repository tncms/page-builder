# LT-2 — Technical Guardrails & Foundation-Preservation Gate

**Status: GOVERNANCE (pre-LT-2). Not an implementation. No LT-2 code has been written.**

These are the preservation rules LT-2 (Persistence) must obey so it never accidentally violates the
**frozen** LT-1 foundation ([`LT-1-ARCHITECTURE-LOCK-ADR.md`](./LT-1-ARCHITECTURE-LOCK-ADR.md),
LT-1-ADR-002). This document ratifies each guardrail against the real LT-1 contracts and confirms the
pre-flight verdict below.

**Pre-flight verdict:** every guardrail and the exit criteria are satisfiable **additively, behind the
frozen boundary, with ZERO changes to any frozen contract** — provided the rehydration discipline in
§R is followed. LT-2 is unblocked to proceed once an implementation spec is provided.

---

## Architectural layers (Guardrail 10)

Dependency direction is one-way; the **core** depends on nothing external, and **persistence is the
lowest layer**, depended on by nothing in the core:

```
core (frozen, pure):   Identity → State → Capability → Descriptor
seams / services:      Reader (returns domain State) · Resolver · Validator · Diagnostics
LT-2 additions:        Persistence (store + migration)  ⟶ behind ⟶  Repository (returns domain contracts)
                       Reader/Repository depend on the core; the core never depends on them.
```

Every LT-2 class must declare its layer in its docblock (e.g. `Layer: Persistence`,
`Layer: Repository`). This keeps the dependency direction explicit and auditable.

---

## Guardrail compliance matrix

| # | Guardrail | Satisfied by (frozen LT-1 contract) | Enforcement |
|---|-----------|--------------------------------------|-------------|
| 1 | Persistence never leaks past the repository boundary; descriptor storage-independent | `LayoutTranslationDescriptor` holds no storage refs; the only seam is `LayoutTranslationStateReaderInterface` (returns `LayoutTranslationStatus`, not rows) | Structural (LT-1) + LT-2 discipline |
| 2 | No business workflow in Value Objects | Descriptor/Identity/State VOs are `final`, immutable, no methods with side effects | Structural (LT-1) |
| 3 | Persistence never invents identity | Identity VOs have private ctors + canonical factories `forOwner` / `forGroup` / `forTranslation`; repo rehydrates via **those** (see §R) | LT-2 discipline (§R) |
| 4 | State dimensions stay orthogonal; never flattened to one status column | `LayoutTranslationStatus` exposes existence / lifecycle / synchronization independently (`toArray()` = 3 keys) | LT-2 discipline: persist 3 fields; rehydrate via §R |
| 5 | Capability stays metadata; never derived from stored state | `LayoutTranslationCapabilityResolverInterface::capabilitiesFor()` takes **no** status (LT-1.5 fix) | Structural (LT-1) |
| 6 | Repository returns domain contracts, never Eloquent/arrays/records | Reader already returns `LayoutTranslationStatus`; LT-2 write-repo returns a domain result type (new, additive) | LT-2 discipline |
| 7 | No hidden clone/diff/merge/sync/baseline in the repository | Those are LT-3/4/5/6; the reader/repo are pure state I/O | LT-2 discipline |
| 8 | Diagnostics read-only | `LayoutTranslationDiagnostics::report()` only reads; never throws/mutates | Structural (LT-1) + LT-2 discipline |
| 9 | Validator deterministic, fail-closed; no persist/repair/auto-create/auto-sync | `LayoutRelationshipValidator` takes only an owner locator; pure | Structural (LT-1) |
| 10 | Every LT-2 class declares its architectural layer | Layer taxonomy above | LT-2 discipline |

None of the above requires modifying a frozen contract.

---

## §R — Rehydration discipline (critical)

To honour Guardrails 3 & 4 **without changing frozen VOs**, LT-2 persistence must reconstruct domain
objects through the **existing** named constructors — never by adding new factories to the frozen VOs:

- **Identity:** read `(owner_type, owner_id, locale)` → `TranslationGroupKey::forOwner(...)` →
  `PageTranslationKey::forGroup(...)` → `LayoutKey::forTranslation(...)`.
- **Status:** persist existence + nullable lifecycle + synchronization as **three** fields, then
  rehydrate by **branching on existence**:
  `NotCreated → notCreated()`, `InvalidReference → invalidReference()`,
  `Exists → exists(lifecycle, synchronization)`.

Adding a `fromArray()` / `fromDimensions()` factory to `LayoutTranslationStatus` (or a raw-string ctor
to an identity key) would touch a frozen contract and **trips the exit criteria** — do not do it.

---

## LT-2 scope reminder (for when it is specced)

The core LT-2 deliverable is the **per-locale layout storage** deferred from LT-1: the additive
`(owner, locale)` binding (Option A `locale` column on `pb_layouts`, or Option B
`pb_layout_translations` table — decision per
[`LT-0-STORAGE-AND-IDENTITY-AUDIT.md`](./LT-0-STORAGE-AND-IDENTITY-AUDIT.md) §6), plus a persistent
`LayoutTranslationStateReaderInterface` implementation and a write repository that returns domain
contracts. All of it lives **behind** the frozen seam; the default-locale single-language behaviour must
remain byte-identical while the flag is off.

---

## Exit criteria (the LT-2 completion gate)

LT-2 must finish **without changing**: Descriptor · Identity · Relationship Model · Capability Model ·
State Model · Validator responsibilities · Diagnostics contract. Any need to modify one of these
**immediately blocks LT-2 and requires a new ADR**.
