# LT-10 — Compatibility Policy

> Documentation only. The compatibility guarantees for the frozen Page Builder Layout Translation subsystem.
> Companion to the [Architecture Freeze Certificate](LT-10-ARCHITECTURE-FREEZE-CERTIFICATE.md).

## Scope

This policy governs the **frozen public contract surface** certified in the freeze certificate §8: the 28
`Contracts` interfaces, the immutable value objects and enums, and the feature-flag behavior. It does **not**
govern INTERNAL implementations (concrete adapters, managers, repositories), which may change freely as long
as the frozen behavior and contracts hold.

## Supported compatibility guarantees

- **Contract stability.** Frozen interface signatures do not change: no method removed, no required parameter
  added, no return type narrowed, no thrown-contract broadened in a breaking way.
- **Immutability.** Value objects and enums remain immutable. Existing enum cases are never renamed or
  removed. Existing VO fields are never removed or repurposed.
- **Dormancy.** With `page-builder.translation.enabled` off, the subsystem stays inert; the flag's default
  remains `false`. Enabling/disabling it never corrupts data.
- **Data safety.** The `pb_layout_translation_*` schema is additive-only; existing columns are never dropped
  or retyped in a way that loses data. Snapshots and history remain append-only and immutable.
- **Direction.** Core is never made to depend on the subsystem; the plugin-owns-document / Core-owns-locale
  split is preserved.

## Backward compatibility rules

1. A caller written against a frozen contract at freeze time keeps working across all maintenance releases.
2. New optional parameters are added **only** with defaults, and only when the contract's ADR permits.
3. New enum cases may be added **only** where consumers are required to handle unknown/`default` cases
   (reserved members already exist for anticipated additions — see Future Directions).
4. Behavioral guarantees (non-destructive translation, fail-closed execution, fail-soft consumption,
   append-only history, payload-safety) are never weakened.

## Additive-only evolution policy

Evolution is **additive**. Permitted without a breaking change:

- new INTERNAL implementations behind existing frozen ports;
- new **separate** interfaces/VOs for genuinely new, opt-in capability (introduced by ADR);
- new reserved enum members handled via `default`;
- new additive, nullable, or defaulted columns/tables under the `pb_layout_translation_*` prefix.

Not permitted outside a major (LT-2) redesign: removing or renaming frozen symbols, changing a frozen
signature, or altering a certified invariant.

## Deprecation policy

- A frozen symbol may be **deprecated** (marked, documented, still functional) via a maintenance note or ADR,
  never silently.
- Deprecation must name a replacement and a rationale.
- Deprecated symbols remain fully operational for at least one **major** cycle after deprecation.

## Removal policy

- Frozen public symbols are **not removed** during maintenance (LT-M*).
- Removal happens only in a future **major** redesign (LT-2), announced by ADR, after a full deprecation
  cycle, with a migration path documented.

## Semantic version expectations

The subsystem follows SemVer intent relative to its frozen surface:

| Change | Version bump | Allowed in maintenance (LT-M*)? |
|---|---|---|
| Bug fix, security fix, perf, docs, INTERNAL refactor | patch | Yes |
| New additive, opt-in capability behind a new contract (ADR) | minor | Yes (additive-capability class) |
| Any breaking contract/invariant change, symbol removal | major (**LT-2**) | No — requires major redesign ADR |

Maintenance releases are **patch/minor** only. A **major** bump is reserved for LT-2 and never happens
implicitly.
