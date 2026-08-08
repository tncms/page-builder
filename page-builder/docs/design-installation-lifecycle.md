# Design Installation Lifecycle (Phase 26.40C)

> **Type:** Technical Specification (normative). **No production code.**
> Defines the **consumer-side** lifecycle of an installed Design Resource/package (Step 5):
> install, upgrade, downgrade, repair, remove, reinstall, rollback, conflict handling — and
> the **dependency lifecycle** (Step 10). Complements the authoring/distribution lifecycle in
> [design-resource-lifecycle.md](design-resource-lifecycle.md).
>
> Builds on: [design-import-export-specification.md](design-import-export-specification.md)
> (atomic import), [design-resource-state-machine.md](design-resource-state-machine.md),
> [design-package-v2.md §5](design-package-v2.md) (dependencies),
> [licensing-boundary.md](licensing-boundary.md).
>
> **Conventions:** RFC 2119 keywords. The install ledger is the existing `InstalledPack`
> registry — **references only, never copies** (26.30/26.31).

---

## 1. Installation states (consumer scope)

| State (KEY) | Meaning |
|---|---|
| `absent` | Not installed |
| `installing` | Import in progress (transient; not yet committed) |
| `installed` | Recorded in the ledger; import committed |
| `active` | Installed + entitlement `granted` + builder-compatible → usable in library/insert |
| `inactive` | Installed but not usable now (entitlement expired or temporarily incompatible) |
| `pending-update` | A newer version is available/queued (see [design-update-lifecycle.md](design-update-lifecycle.md)) |
| `broken` | Installed but failing integrity/dependency/compat checks |
| `removing` | Removal in progress (transient) |

Transitions among these follow the state-machine guards (entitlement/compat/permission).
`installing`/`removing` are transient and never persist across a failed atomic operation.

---

## 2. Operations

### 2.1 Install
- Runs the full import pipeline (import-export §A): integrity → structural → asset boundary →
  compatibility → dependencies → migration → entitlement → conflict → **atomic commit**.
- On success: assets rehosted (content-addressed), resources landed in
  `ImportedTemplateDefinitionSource`, kit token sets applied, ledger updated → `installed`
  (→ `active` if entitled + compatible).
- On any blocking `reason`: nothing changes (`absent` preserved); structured reasons returned.
- **Idempotent:** installing an already-installed identical version (same digest) is a no-op.

### 2.2 Upgrade / Downgrade
- Change the installed version of a RID (details, triggers, auto vs manual in
  [design-update-lifecycle.md](design-update-lifecycle.md)).
- Both run as an **atomic replace**: validate + resolve the target version fully, then swap;
  on failure the previously installed version remains intact (rollback).
- The prior version's content-addressed package is **retained** to enable rollback (§2.7).
- Downgrade requires explicit opt-in (import-export §A.9) and MUST pass the target version's
  compatibility/entitlement gates.

### 2.3 Repair
- Re-resolve dependencies, re-verify `CHECKSUMS`, re-run compatibility for an `installed`/
  `broken` copy. If it passes → return to `active`/`installed`; else remain `broken`
  (state-machine §6). Repair is atomic and never partially mutates the library.

### 2.4 Remove
- Remove the ledger reference (and, for kits, the kit record). Because content is
  reference-based and assets are content-addressed/shared, removal MUST NOT delete assets still
  referenced by other installs or by content already inserted into documents.
- **Copy-on-insert protection:** removing an installed resource NEVER affects content the user
  already inserted into pages (design-resource-model §6). Removal affects the *library entry*,
  not placed content.
- Removal is atomic; partial removals are forbidden.

### 2.5 Reinstall
- `remove` then `install` of the same (or a chosen) version, as one logical, atomic
  operation used for recovery. The retained package makes reinstall offline-capable.

### 2.6 Conflict handling
- Applies when installing a RID that already exists (import-export §A.9):
  - same version + same digest → **duplicate** (idempotent skip, warning);
  - same version + different digest → **conflict** (blocking reason unless explicit overwrite
    policy);
  - different version → **version mismatch** (policy: keep-both / newer-wins / explicit
    downgrade).
- The conflict policy is an **input** to the operation and MUST yield a deterministic outcome.

### 2.7 Rollback
- Return an installed copy to its **previous version** using the retained content-addressed
  package. Atomic; audited. Rollback is the standard recovery for a failed/unwanted update.
- If no prior version is retained (first install), rollback = remove.

---

## 3. Dependency lifecycle (Step 10)

Kits and resources may depend on other resources/packages
([design-package-v2.md §5](design-package-v2.md)). Dependencies have their own lifecycle,
evaluated at install, update, and repair.

| Situation | Detection | Resolution (normative) |
|---|---|---|
| **Missing (required)** | resolve finds no satisfying install/bundle | Blocking `reason` `dependency.missing`; install fails atomically. A kit MAY install available members and record the gap only if the missing member is **optional**. |
| **Missing (optional)** | as above, `optional=true` | Non-blocking `warning`; dependent installs **degraded**, gap recorded in ledger. |
| **Upgraded** | a dependency's version changes | Re-evaluate dependents' ranges; if still satisfied → no action; if broken → dependent → `broken` (repair/update offered). |
| **Removed** | a depended-upon resource is removed while dependents exist | Removal MUST warn and either be blocked or cascade per policy; dependents referencing it become `broken` until repaired. Default: block removal of a resource with active required dependents. |
| **Incompatible** | dependency present but version out of range | Blocking `reason` `dependency.version_out_of_range` (required) or `warning` (optional). |
| **Circular** | dependency graph has a cycle | Blocking `reason` `dependency.cycle`; MUST be rejected at install/publish (graphs are acyclic). |
| **Broken** | a dependency is itself `broken` | Dependent treated as `broken` transitively; repair resolves bottom-up. |

Rules:
- Resolution is **deterministic** and MUST NOT fetch remotely in the core installer (remote
  resolution is a reserved `remote`/`marketplace`/`cloud` concern).
- Dependency graphs MUST be acyclic; transitive resolution is bounded (no unbounded fan-out).
- A dependency change MUST NOT silently alter a dependent's inserted content (copy-on-insert).

---

## 4. Entitlement & compatibility interplay

- **Entitlement** (C8/resolver) gates install/update and toggles `active ↔ inactive` on
  expiry; it never deletes installed content (grandfathering, licensing-boundary §4.1).
- **Compatibility** (26.27 gate) toggles `active ↔ inactive`/`broken` when the builder version
  or required components/features change; unresolved tokens only warn (still renders via
  fallback).
- Both are pure predicates over a snapshot; no wall-clock/randomness.

---

## 5. Atomicity & determinism (recap)

- Every operation (install/upgrade/downgrade/repair/remove/reinstall/rollback) is **atomic**:
  it fully succeeds or leaves the library byte-identical to its prior state.
- Transient states (`installing`/`removing`) never persist through a failure.
- Given the same library snapshot + package + policy inputs, outcomes are reproducible.

---

## 6. Audit

Every operation and dependency-lifecycle event emits an audit record (operation, actor,
source, versions from→to, reasons/warnings, outcome) per
[design-audit-traceability.md](design-audit-traceability.md).

---

## 7. Conformance checklist

1. Consumer states implemented; transient states never persist through failure (§1, §5).
2. Install/upgrade/downgrade/repair/remove/reinstall/rollback all atomic; prior version
   retained for rollback (§2).
3. Removal and dependency changes never affect already-inserted content (copy-on-insert) (§2.4, §3).
4. Dependency lifecycle handles missing/optional/upgraded/removed/incompatible/circular/broken
   deterministically; graphs acyclic; no remote fetch in core (§3).
5. Entitlement toggles active/inactive without deleting content; compatibility gates activity (§4).
6. Every operation audited (§6).

---

*End of design-installation-lifecycle.md*
