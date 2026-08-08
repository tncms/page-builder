# LT-10 — Extension Guide

> Documentation only. How any future capability for Page Builder Layout Translation must be classified and
> approved. Companion to the [Architecture Freeze Certificate](LT-10-ARCHITECTURE-FREEZE-CERTIFICATE.md),
> [Maintenance Policy](LT-10-MAINTENANCE-POLICY.md), and the permanent
> [Plugin/Core Boundary](../../../docs/architecture/PLUGIN_CORE_BOUNDARY.md).

## How to use this guide

Before building anything, classify the need into exactly **one** of the six classes below and follow its
approval path. When in doubt, choose the **more restrictive** class. Nothing bypasses this classification.

## The six classes

### 1. Maintenance
Bug fix, security fix, performance improvement, documentation, or INTERNAL refactor behind unchanged frozen
contracts and invariants.
- **Touches frozen surface?** No.
- **Approval:** none beyond normal review. Ships as **LT-M\*** (patch).
- **Rule:** must not change any signature, invariant, or observable behavior of existing callers.

### 2. Additive capability
A new, **opt-in**, **separate** capability that adds behavior without touching any frozen symbol or invariant
(e.g. an alternate INTERNAL backend behind an existing port, a reserved-enum member, an additive column).
- **Approval:** a short **capability ADR**. Ships as **LT-M\*** (minor).
- **Rule:** existing callers are unaffected; the capability is disabled/absent by default.

### 3. Major redesign
Any change that removes/renames a frozen symbol, breaks a frozen contract signature, weakens a certified
invariant, or replaces the execution/coordinator authority model.
- **Approval:** a **major redesign ADR**; a full deprecation cycle; a migration path. Ships as **LT-2**
  (major). Never done in maintenance.

### 4. Core evolution
The need is actually a **Core** capability the subsystem lacks (locale, translation groups, localized-entity
lifecycle, language configuration).
- **Approval:** **STOP.** Produce an architecture audit of the gap → propose a **dedicated Core Evolution
  phase** → wait for approval → Core evolves independently → the plugin then consumes the new public Core
  surface. Governed by [Plugin/Core Boundary](../../../docs/architecture/PLUGIN_CORE_BOUNDARY.md) Rule 4.
- **Rule:** the plugin **never** implements a Core capability inside itself.

### 5. Plugin extraction
A second plugin genuinely needs this synchronization discipline, requiring the subsystem to leave Page
Builder and become an independently packaged engine.
- **Approval:** an **extraction ADR** satisfying [Plugin/Core Boundary](../../../docs/architecture/PLUGIN_CORE_BOUNDARY.md)
  Rules 6–7 — approved owner, independent packaging, plugin-neutral versioned contracts, compatibility policy,
  release lifecycle, and **at least one second production consumer**.
- **Rule:** until that ADR is approved and executed, the subsystem stays inside Page Builder.

### 6. Shared-platform proposal
A proposal to present or operate this as a shared "TN CMS Translation Platform," shared runtime, generic
engine, or plugin SDK.
- **Approval:** only after class 5 (extraction) is fully satisfied. A shared platform exists **only** when all
  six criteria of Boundary Rule 6 are met and proven.
- **Rule:** documentation may never advertise shared-platform status before it exists (Boundary Rule 5).

## Classification decision table

| Need | Class | Approval path | Version |
|---|---|---|---|
| Fix / perf / security / docs / internal refactor | 1 Maintenance | normal review | patch (LT-M\*) |
| New opt-in capability, no frozen symbol touched | 2 Additive | capability ADR | minor (LT-M\*) |
| New public contract / realize a reserved capability | 2 Additive → ADR | new ADR | minor (LT-M\*) |
| Break frozen contract/invariant; remove symbol | 3 Major | major redesign ADR + deprecation | major (LT-2) |
| Missing Core capability | 4 Core evolution | audit → Core Evolution phase | Core (separate roadmap) |
| Second plugin needs the engine | 5 Extraction | extraction ADR (Rules 6–7) | new package |
| Call it a shared platform | 6 Shared-platform | after extraction, all 6 criteria proven | new platform |

## Non-negotiable rules for every class

- Preserve the frozen invariants (non-destructive translation, fail-closed execution, fail-soft consumption,
  append-only history, payload-safety, immutability).
- Preserve the dependency direction: Core is never made to depend on the subsystem.
- Preserve the ownership matrix: no responsibility crosses the Core/plugin line.
- Never introduce documentation overclaim.
