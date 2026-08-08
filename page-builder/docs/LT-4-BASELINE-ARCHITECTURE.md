# LT-4 — Baseline & Structural Correspondence Architecture

**Status: IMPLEMENTED (LT-4). Additive, flag-gated. No frozen LT-1/LT-2/LT-3 contract changed.**

Establishes the first authoritative provenance model (per
[`LT-3-FINAL-GOVERNANCE.md`](./LT-3-FINAL-GOVERNANCE.md) Invariant 1): stable cross-locale node
correspondence + an immutable, content-addressed source baseline — the foundation future diff (LT-5)
and synchronization (LT-6) build on. No diff, sync, plan, merge, execution, or UI. Decisions from
[`LT-4-BASELINE-AUDIT.md`](./LT-4-BASELINE-AUDIT.md) resolved below.

---

## Decision A — correspondence (A1, adopted)

Cross-locale node correspondence is a stable `data['_origin']` id (per frozen LT-0-ADR-001 D6 — the
normalizer preserves `data`). `NodeCorrespondence` (`src/LayoutTranslation/Correspondence/`):

- `stamp(payload)` — immutably adds `data['_origin'] = node id` to every node that has a physical id and
  no origin. **Applied to the TARGET only; the source document is never mutated.** Idempotent (keeps an
  existing origin).
- `originOf(node)` — stamped origin → else physical id → else null.
- `report(payload)` — coverage: total / stamped / target-owned / duplicate origins (for diagnostics).

A node with no origin is **target-owned** (added independently of the source) and must never be
overwritten by future source synchronization. Verified: origin survives physical-id regeneration.

## Decision B — baseline storage (B2, adopted — *corrected from the audit's B1*)

The audit recommended B1 (columns on the binding), but that would force the **frozen** LT-2 binding
model/repository/VO to carry baseline — violating "LT-2 remains frozen." **B2 is used instead:** a
**separate** LT-4-owned table `pb_layout_translation_baselines`, so LT-2 is untouched.

- **Fingerprint** (`BaselineFingerprint`, per D7): `sha256(canonicalJson(payload))` computed over a
  canonical form with physical ids **and** correspondence metadata stripped — so a source and a target
  derived from it hash **identically**. Verified: id-independent, origin-independent, content-sensitive.
- **`LayoutBaseline`** (domain VO): `pageTranslationKey`, `sourceLayoutId`, `fingerprint`, `capturedAt`.
- **Storage** (`pb_layout_translation_baselines`, additive migration, **created not executed**):
  `unique(page_translation_key)`, `source_layout_id`, `fingerprint`, `captured_at`. **No FK to
  `pb_layouts`** — the baseline is a content-addressed snapshot and the source stays independently
  deletable/editable (LT-3 §3.7). Repository returns the domain `LayoutBaseline`, never a model.

## Capture service

`LayoutBaselineCaptureService::capture(targetKey)` — flag-gated, atomic (one transaction via the LT-3
`TransactionRunnerInterface`): resolve the target binding (LT-2 reader) → read the source payload (LT-3
source reader) → compute the source fingerprint → **stamp the target's correspondence** and save its
draft → **record the baseline**. Typed `BaselineCaptureResult` /
`BaselineCaptureOutcome` (`Captured`, `FeatureDisabled`, `TargetNotBound`, `SourceUnavailable`,
`IntegrityFault`, `PersistenceFailure`). It never mutates the source, computes no diff, and performs no
synchronization. `IN_SYNC` from LT-3 is a creation marker; **this baseline is the authoritative
provenance.**

## Diagnostics

`LayoutBaselineDiagnostics::report(targetKey)` — never-throws, read-only, non-repairing: baseline
presence + fingerprint, bound state, and correspondence coverage (incl. orphaned/ambiguous origins). No
payloads logged.

## Dependency direction & layering

Translation → Page Builder only. Correspondence + baseline are Translation-layer; they consume the
generic `LayoutStore` and the frozen LT-2/LT-3 seams. No Page Builder core or Core file is aware of them.

## Non-goals

No structural diff (LT-5); no sync plan/execution/merge/conflict (LT-6/7); no UI (LT-8); no auto source
tracking; no Core change; no frozen-contract change; no baseline via Core `cms.revision` (prunes).
