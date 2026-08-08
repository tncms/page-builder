# Design Cloud Synchronization (Phase 26.40C)

> **Type:** Technical Specification (normative). **No production code.**
> Defines the cloud synchronization lifecycle (Step 13): local ↔ cloud sync, conflict
> resolution, merge policy, offline mode, recovery, and version-mismatch handling.
> **Cloud is a reserved seam** (`cloud` source, Phase 26.40A/B) — this document specifies the
> contract a future cloud provider MUST satisfy; nothing here is built now.
>
> Builds on: [design-distribution-model.md](design-distribution-model.md),
> [design-installation-lifecycle.md](design-installation-lifecycle.md),
> [design-import-export-specification.md](design-import-export-specification.md),
> [design-audit-traceability.md](design-audit-traceability.md).
>
> **Conventions:** RFC 2119 keywords. "Local" = a builder install's library; "Cloud" = the
> remote library service (reserved).

---

## 1. Scope & invariants

- Cloud sync operates over **references and packages**, never over the frozen runtime. It MUST
  NOT change serialization, CommandBus/BuilderState, or resource identity.
- Synced units are RIDs + versions + C8 metadata + packages (content-addressed). C1–C7 payloads
  are identical to any other channel; cloud is a **transport/mirror**, not a new format.
- Sync MUST be **deterministic** given the same local/cloud snapshots and policy; conflict
  resolution is rule-based, not heuristic.
- Cloud is **reserved**: the core builder MUST function fully offline; cloud sync is additive.

---

## 2. Sync model

A two-way mirror between a local library and a cloud library scope (per user / per org).

```
Local library  ⇄  Sync engine  ⇄  Cloud library
   (RIDs,           (diff +          (RIDs,
   versions,        resolve +         versions,
   packages,        transfer)         packages,
   ledger)                            ledger)
```

### 2.1 Sync unit & state

```ts
interface SyncRecord {
  rid: string
  version: string
  digest: string          // content-addressed package digest
  syncState: 'in-sync' | 'local-ahead' | 'cloud-ahead' | 'conflict' | 'pending' | 'error'
  lastSyncedAt?: string   // ISO-8601 (provider-supplied; not runtime now())
}
```

- `in-sync`: local and cloud agree on version+digest.
- `local-ahead` / `cloud-ahead`: one side has a newer version the other lacks.
- `conflict`: both sides changed divergently (§4).
- `pending`/`error`: transfer queued or failed (recoverable, §6).

---

## 3. Sync operations

| Operation | Direction | Effect |
|---|---|---|
| `push` | local → cloud | Upload local-ahead RIDs/versions/packages |
| `pull` | cloud → local | Download cloud-ahead RIDs/versions (then install via the normal import pipeline) |
| `reconcile` | both | Compute the diff, classify each RID's `syncState`, resolve per policy |

Normative:
- **Pull MUST route through the standard import pipeline** (validation → compatibility →
  entitlement → atomic commit). Cloud content is not trusted more than any package.
- **Push** transfers content-addressed packages; identical digests are deduplicated (idempotent).
- Every operation is atomic per RID and audited.

---

## 4. Conflict resolution & merge policy

A **conflict** arises when the same RID diverges on both sides (e.g. local edited a user
resource while cloud received a different edit).

### 4.1 Granularity
- Conflicts are resolved at the **RID+version** granularity. Resources are treated as
  **atomic units** (copy-on-insert; id-free trees). There is **no automatic deep three-way
  merge of tree contents** — merging arbitrary builder trees is unsafe and is explicitly out of
  scope.

### 4.2 Resolution policy (deterministic, ordered)
1. **Identical digest** → not a conflict (idempotent `in-sync`).
2. **One side is a strict version ancestor** (e.g. local `1.0.0`, cloud `1.1.0`, same lineage)
   → fast-forward to the newer version (treated as an update via the update pipeline).
3. **Divergent same-version, different digest** → **conflict**; apply the configured policy:
   - `manual` (default): surface both; user chooses keep-local / keep-cloud / keep-both
     (keep-both forks to a new RID via explicit rename — user action).
   - `prefer-cloud` / `prefer-local`: deterministic auto-pick per org/user policy.
   - `newest-wins`: pick higher version; equal version falls back to `manual`.
4. The chosen side is applied atomically via install/update; the other is retained in history
   for rollback.

### 4.3 No silent data loss
- A conflict resolution MUST NOT silently discard the losing side: it is retained (versioned/
  audited) so the user can recover it. Inserted content is never touched (copy-on-insert).

---

## 5. Offline mode

- The builder MUST be **fully functional offline**: browse installed resources, insert, edit —
  all without cloud.
- Local changes accrue as `local-ahead`/`pending`; they sync on reconnection.
- Offline-imported packages behave like any `imported` source; they sync to cloud on next
  `push` if in scope.
- No feature depends on cloud availability; cloud is strictly additive.

---

## 6. Recovery

| Failure | Recovery |
|---|---|
| Transfer error (network) | `error`/`pending`; retried with backoff; idempotent (content-addressed) so retries never duplicate |
| Partial pull | Atomic import means a failed pull leaves local unchanged; re-pull |
| Corrupted cloud package (digest mismatch) | Rejected at V1 integrity; not installed; flagged |
| Divergence after crash | `reconcile` recomputes `syncState` from digests deterministically |
| Lost local | Full `pull` reconstructs the local library from cloud (references + packages) |

Recovery is deterministic and audited; no partial/duplicated state results from retries.

---

## 7. Version mismatch

- If a cloud package targets a `schemaVersion`/format newer than the local builder supports →
  blocking `reason` on pull (version-compat §5); the local library is unchanged and the user is
  told to upgrade the builder. Older schema → migration seam.
- Entitlement is re-checked on pull (a cloud resource still requires entitlement to install);
  expired entitlement blocks new pulls but grandfathers existing installs.

---

## 8. Determinism, security, audit

- `reconcile` is a pure function of `(localSnapshot, cloudSnapshot, policy)` → sync plan; no
  wall-clock/randomness in the decision.
- All transferred packages pass the full validation pipeline; signatures verified per trust
  policy (validation-pipeline §2).
- Every push/pull/reconcile/conflict-resolution is audited (rid, from→to version/digest,
  decision, actor/policy) per [design-audit-traceability.md](design-audit-traceability.md).

---

## 9. Conformance checklist

1. Cloud is a reserved additive transport; builder fully functional offline (§1, §5).
2. Pull routes through the standard import pipeline; content-addressed dedup; atomic per RID (§3).
3. Conflicts resolved at RID+version atomically; no automatic deep tree merge; no silent loss (§4).
4. Recovery deterministic + idempotent; retries never duplicate; lost-local rebuildable (§6).
5. Version mismatch fails cleanly / migrates; entitlement re-checked with grandfathering (§7).
6. Everything deterministic + audited; inserted content never touched (§4.3, §8).

---

*End of design-cloud-synchronization.md*
