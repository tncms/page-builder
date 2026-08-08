# LT-10 — Maintenance Policy

> Documentation only. What is permitted in maintenance mode for the frozen Page Builder Layout Translation
> subsystem, and what escalates to an ADR or a major redesign. Companion to the
> [Architecture Freeze Certificate](LT-10-ARCHITECTURE-FREEZE-CERTIFICATE.md) and the
> [Compatibility Policy](LT-10-COMPATIBILITY-POLICY.md).

## Mode

The subsystem is **RELEASED ARCHITECTURE — maintenance only**. Future work is numbered **LT-M1, LT-M2, …**.
Maintenance work must preserve every frozen contract and invariant.

## Allowed without an ADR (routine maintenance — LT-M*)

- **Bug fixes** — correcting behavior to match the certified invariants and contracts (no signature change).
- **Performance improvements** — INTERNAL only, behind frozen ports, with identical observable behavior
  (e.g. query batching, caching, avoiding N+1). No contract or invariant change.
- **Security fixes** — input hardening, authorization tightening, dependency updates. If a security fix
  *must* change a frozen contract, it escalates to an ADR (documented, expedited).
- **Documentation** — clarifications, examples, corrections. Must never introduce overclaim (see the
  [Plugin/Core Boundary](../../../docs/architecture/PLUGIN_CORE_BOUNDARY.md), Rule 5).
- **INTERNAL refactor** — swapping/adding concrete adapters, managers, repositories behind unchanged frozen
  interfaces; test additions; tooling.

All of the above are **patch**-level (or **minor** for a purely additive, opt-in capability — see below).

## Allowed additive capabilities (minor — LT-M*, ADR-lite)

New, **opt-in**, **separate** capability that does not touch any frozen symbol or invariant may ship as a
minor release with a short capability ADR:

- a new INTERNAL backend behind an existing frozen port (e.g. an alternate snapshot store);
- a new reserved-enum member handled via `default`;
- a new additive `pb_layout_translation_*` column/table (nullable/defaulted).

These must not alter existing behavior for existing callers.

## Requires a new ADR

- Adding a **new public contract** (interface / extension point).
- Realizing any **reserved capability** (baseline document persistence, snapshot capability implementation,
  reversal execution, replay, AI-assisted merge, history-browser UI) — each is architecture-frozen but
  implementation-gated and needs its contract, invariants, and layer defined by ADR.
- Any change to a **frozen invariant** or a **frozen contract signature**.
- Any change to the **dependency direction** or the **ownership matrix**.

## Requires LT-2 (future major redesign)

- Removing or renaming any frozen public symbol.
- Breaking a frozen contract signature or weakening a certified invariant.
- Replacing the execution/coordinator authority model.
- Generalizing the document shape beyond layout documents (content-shape generalization).

LT-2 is a **major** version, announced by ADR, after a full deprecation cycle, with a migration path.

## Requires Core evolution (never done inside this plugin)

If maintenance reveals that **Core** lacks a needed capability (locale, translation groups, localized-entity
lifecycle, language configuration), the subsystem **stops** and follows
[Plugin/Core Boundary](../../../docs/architecture/PLUGIN_CORE_BOUNDARY.md) Rule 4: audit → propose a dedicated
Core Evolution phase → wait → Core evolves independently → the plugin consumes the new public Core surface.
Core capability is **never** re-implemented inside the plugin.

## Decision summary

| Need | Path | Version |
|---|---|---|
| Bug / security / perf / docs / internal refactor | LT-M* directly | patch |
| New opt-in capability, no frozen symbol touched | LT-M* + capability ADR | minor |
| New public contract / reserved capability realized | new ADR | minor |
| Break a frozen contract or invariant; remove a symbol | **LT-2** | major |
| Missing Core capability | Core Evolution phase (separate roadmap) | Core |
| Cross-plugin reuse / shared platform | Extraction ADR (Boundary Rules 6–7) | new package |
