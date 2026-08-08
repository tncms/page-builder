# Design Update Lifecycle (Phase 26.40C)

> **Type:** Technical Specification (normative). **No production code.**
> Defines update behavior for installed resources/packages (Step 6) and the **version
> evolution** model (Step 9): upgrade, migration, deprecation, sunset, replacement, and
> compatibility guarantees.
>
> Builds on: [design-installation-lifecycle.md](design-installation-lifecycle.md),
> [design-version-compatibility.md](design-version-compatibility.md),
> [design-resource-lifecycle.md](design-resource-lifecycle.md),
> [licensing-boundary.md](licensing-boundary.md).
>
> **Conventions:** RFC 2119 keywords. Versions are semver `X.Y.Z`.

---

## 1. Update triggers & modes

| Mode (KEY) | Trigger | Default policy |
|---|---|---|
| `manual` | User initiates | Always allowed (subject to gates) |
| `auto` | Channel/policy pushes updates | Opt-in per install/channel |
| `security` | A fix flagged security-critical | SHOULD be expedited; MAY be forced by policy (enterprise) |
| `preview`/`beta` | Pre-release channel | Opt-in only |
| `dependency` | A dependency update requires a dependent update | Cascaded per §5 |

Update modes are **policy**, not new lifecycle states. An available update moves the consumer
copy to `pending-update` (installation §1) until applied or dismissed.

---

## 2. Update by semver class

| Class | Meaning | Default behavior |
|---|---|---|
| **patch** (`x.y.Z`) | Fixes, no contract change | Safe to auto-apply; no migration expected |
| **minor** (`x.Y.z`) | Additive, backward-compatible | Safe to apply; tolerant reader absorbs additions |
| **major** (`X.y.z`) | Breaking | MUST NOT auto-apply by default; requires explicit consent + may require migration (§4) |

Normative:
- `auto`/`security` updates MAY apply patch/minor automatically (per opt-in). **Major** updates
  MUST require explicit user/admin consent (they can change structure/requirements).
- Every update, regardless of class, runs the **full validation + compatibility + entitlement +
  dependency** gates of the install pipeline before commit.

---

## 3. Update execution (atomic replace)

An update is an **atomic replace** of the installed version:

1. Resolve the target version from the channel (or provided package).
2. Run gates: integrity → structural → asset boundary → compatibility → dependencies →
   migration → entitlement → conflict.
3. If all pass: swap atomically; retain the **previous** content-addressed package for
   rollback; update the ledger; re-activate (`active`) if entitled + compatible.
4. If any blocking `reason`: **abort**; the previously installed version remains intact and
   `active`; reasons returned. No partial update.

Guarantees:
- **No partial updates.** Failure leaves the prior version byte-identical.
- **Rollback available.** `rollback` restores the retained prior version atomically
  (installation §2.7).
- **Copy-on-insert unaffected.** Updating a library resource NEVER rewrites content the user
  already inserted into documents (design-resource-model §6). Updates change the *library
  entry / future inserts*, not placed content.

---

## 4. Migration during update (Step 9)

- When a target version bumps the payload `schemaVersion` or package format, the update routes
  through the migration provider (26.27 `nullMigrationProvider` seam;
  [design-version-compatibility.md §6](design-version-compatibility.md)).
- Migrations MUST be **pure and deterministic** (same input → same migrated artifact) and are
  applied **before** commit. A missing migration path → blocking `reason`
  `version.no_migration_path`; the update aborts, prior version retained.
- v1 `tncms.template-package` → v2 mapping (templates → page/section) is defined even with the
  null provider.

---

## 5. Dependency-driven updates

- If updating resource A requires a newer dependency B (range no longer satisfied), the updater
  resolves B first (deterministically, no remote fetch in core). If B cannot be satisfied →
  blocking `reason` `dependency.version_out_of_range`; the update aborts.
- Updating B MAY move dependents to `pending-update`/`broken` if B's new version breaks their
  ranges; repair/update is then offered (installation §3).
- Cascades are bounded and acyclic; the updater MUST NOT loop.

---

## 6. Version evolution: deprecation, sunset, replacement (Step 9)

Evolution operates on the **authoring/distribution** lifecycle
([design-resource-lifecycle.md](design-resource-lifecycle.md)); consumers are always
grandfathered.

| Event | Publisher action | Consumer effect |
|---|---|---|
| **New version** | Publish `X.(Y+1).Z` / `(X+1).0.0` of same RID | Offered as an update (per mode/class); coexists with prior published versions |
| **Deprecation** | Mark a version/RID `deprecated` (`supersededBy` optional) | Still installable/usable; flagged as discouraged; update to successor suggested |
| **Sunset** | After a window, `deprecated → archived` | No new installs; existing installs keep working (grandfathering) |
| **Replacement** | Publish a new RID; deprecate old with `supersededBy: newRID` | Consumers offered migration to the new RID; old content already inserted is unaffected |

Normative:
- Deprecation/sunset/replacement **MUST NOT** break installed/active copies or inserted
  content. They govern **distribution and update suggestions**, not existing usage.
- Replacement across RIDs is a *suggested migration*, never automatic (RIDs are distinct
  resources; the user opts in).

---

## 7. Compatibility guarantees (Step 9)

- **Patch/minor** updates MUST preserve backward compatibility (tolerant reader for additive
  fields; version-compat §5).
- **Major** updates MAY break compatibility and therefore require consent + possibly migration.
- Forward incompatibility (a package newer than the builder supports) fails cleanly with a
  blocking `reason` — never a partial/forced update.
- Unresolved tokens after an update only **warn** (fallback renders); they never block an
  otherwise-valid update.

---

## 8. Determinism & audit

- Given the same installed snapshot, target version, and policy inputs, an update's outcome
  (applied/aborted, reasons/warnings, resulting version) is reproducible.
- Every update, rollback, deprecation-driven suggestion, and dependency cascade is audited
  (from→to versions, source, trigger mode, reasons) per
  [design-audit-traceability.md](design-audit-traceability.md).

---

## 9. Conformance checklist

1. Update modes are policy over `pending-update`; not new lifecycle states (§1).
2. Patch/minor MAY auto-apply (opt-in); major requires explicit consent (§2).
3. Updates are atomic-replace with prior version retained; no partial updates; rollback works (§3).
4. Migrations pure/deterministic, pre-commit; missing path aborts with reason (§4).
5. Dependency-driven updates resolve deterministically, bounded, acyclic (§5).
6. Deprecation/sunset/replacement never break installed content; replacement is opt-in (§6).
7. Compatibility guarantees per semver class; forward-incompat fails cleanly; token misses warn (§7).
8. Everything deterministic + audited (§8).

---

*End of design-update-lifecycle.md*
