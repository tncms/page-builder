# LT-3 — Binding Lifecycle Governance (LOCK)

**Status: RATIFIED & LOCKED (LT-3).** Governs every current and future workflow that touches a
translation binding. Complements [`LT-2-GUARDRAILS.md`](./LT-2-GUARDRAILS.md) and the frozen
[`LT-1-ARCHITECTURE-LOCK-ADR.md`](./LT-1-ARCHITECTURE-LOCK-ADR.md).

## 3.1 Binding meaning

A translation binding is a **canonical relationship record**:
`PageTranslationKey → LayoutKey → pb_layouts.id`. It is **not** an audit log, a baseline, a layout
payload, a translation job, a synchronization record, or an authorization grant.

## 3.2 Binding lifetime

A binding exists for as long as the Page Translation owns that Page Builder layout relationship. It is
**never silently removed** because the layout is unpublished, the target became draft, synchronization
state changed, the source changed, a theme changed, the plugin was temporarily disabled, or the feature
flag was disabled. **Lifecycle-state changes never imply binding deletion.**

## 3.3 Layout deletion

The restrictive foreign key (`layout_id → pb_layouts.id`, `restrictOnDelete`) is intentional. A
referenced layout **must not be physically deleted while a binding exists** — the system fails closed:

```
Delete referenced layout → binding exists → operation blocked
```

The operator/application must first make an explicit relationship-removal decision. **No cascade
deletion is ever added.**

## 3.4 Binding removal

`removeBinding()` is a low-level persistence capability, **not** an automatically permitted business
action. LT-3 exposes **no** general binding deletion to UI or public runtime. Any future removal
workflow must explicitly define authorization, eligibility, layout disposition, audit, recovery, and
effect on baselines/synchronization history. Until then, binding removal is an internal, explicit,
exceptional operation.

## 3.5 No premature archive model

No soft deletes, archived bindings, tombstones, or historical versions are introduced unless a real
later-phase workflow requires them. For LT-3: active bindings remain present; a **failed clone creates
no binding**; rollback removes all writes of the failed attempt; **no partial binding is ever retained.**

## 3.6 Orphan policy

An orphaned or corrupted binding is an **integrity fault**: observable through diagnostics
(`LayoutTranslationBindingValidator`), rejected by write workflows, **never silently repaired, never
silently deleted**. Repair tooling is outside LT-3.

## 3.7 Source deletion / target independence

After a successful clone the target is an **independent** document. A target must not depend at runtime
on the continued existence of its source. Deleting or changing the source later must never remove or
mutate the target. Any future source→target relationship arrives separately via the baseline (LT-4) and
synchronization (LT-6) phases.
