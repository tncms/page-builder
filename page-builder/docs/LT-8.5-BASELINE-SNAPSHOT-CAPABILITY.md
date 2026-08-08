# LT-8.5 — Baseline Snapshot Capability Architecture

**Status: ARCHITECTURE ONLY (LT-8.5). No implementation, no UI, no Workspace Runtime change, no
Coordinator change, no Diff/Planner change, no frozen-contract change.** Designs a platform capability that
retains and provides historical layout **documents** for every consumer that needs them, closing the gap
LT-8D surfaced (LT-4 persists a baseline *fingerprint*, never the *document*). The capability **plugs
underneath** the frozen `BaselineDocumentSourceInterface` seam — that seam is unchanged. **Stop after
architecture.**

---

## 0. Position — the gap, and where the capability sits

LT-4 (Decision B2) persists the baseline as a **content-addressed fingerprint** only; the source row holds
the *current* source, not the last-synced snapshot; LT-7 history is **payload-safe** (fingerprints, origins
and enums — never content). So no consumer can obtain a historical layout *document*. LT-8D wired the review
provider against a `BaselineDocumentSource` seam whose default returns `null` (fail-soft). LT-8.5 supplies
the missing content plane **beneath that seam**.

```
   Review Workspace   History Browser   Replay   Undo   Rollback   AI-assisted merge
          │                  │             │       │       │              │
          └──────────────────┴──────┬──────┴───────┴───────┴──────────────┘
                                     │  read documents BY FINGERPRINT
                                     ▼
        BaselineDocumentSourceInterface  ← FROZEN seam (unchanged; consumer contract)
                                     │  (new impl bound here in a later phase)
                                     ▼
        ┌───────────────────────────────────────────────────────────────┐
        │  LT-8.5  BASELINE SNAPSHOT CAPABILITY                          │
        │                                                               │
        │   LayoutSnapshotStore  (content-addressed store, CAS)         │
        │        ▲ capture (decorators on existing write seams)         │
        │        │                                                       │
        │   baseline/history rows already hold the FINGERPRINTS that     │
        │   are this store's reference keys → zero new identity space    │
        └───────────────────────────────────────────────────────────────┘
```

The whole capability is **additive and dormant**: with no snapshots present, the seam still returns `null`
and the workspace stays fail-soft blocked — exactly today's behaviour. As snapshots accumulate, consumers
begin to resolve documents. Nothing above the seam changes.

---

## 1. Design principles (proposed invariants)

| # | Invariant |
|---|---|
| **INV-LT-8.5-01** | **Content-addressed & immutable.** A snapshot blob is addressed by a digest of its exact bytes and is write-once — never mutated. Consumer *references* are the existing `BaselineFingerprint` values (`sha256-…`) already stored on baseline/history rows; the capability introduces no new reference identity. |
| **INV-LT-8.5-02** | **Plugs underneath a frozen seam.** The capability changes no frozen contract. It provides a new implementation *beneath* `BaselineDocumentSourceInterface` and captures *only* through decorators of existing write seams. No Workspace Runtime, Coordinator, Diff/Planner, or Presentation code is edited. |
| **INV-LT-8.5-03** | **Metadata payload-safety preserved.** Plan, provenance, acceptance, and history stay content-free and reference snapshots by fingerprint only. Content lives *solely* in the snapshot store — a separate plane. The CAS is not part of any payload-safe surface. |
| **INV-LT-8.5-04** | **Fail-soft absence, fail-closed integrity.** A missing snapshot yields `null` (→ the seam returns `null` → workspace fail-soft). A snapshot whose re-hash does not match its reference is **never** returned (fail-closed). Absence never fabricates or approximates content. |
| **INV-LT-8.5-05** | **Reversibility retention.** A snapshot referenced by a live baseline or by any un-pruned append-only history row is **pinned** and never reclaimed — preserving INV-LT-05 (reversibility) and INV-LT-7-EXEC-02 (history sufficiency). |
| **INV-LT-8.5-06** | **One historical-content path.** Every consumer reads documents through the single snapshot store; none implements a second content-loading path (the single-path discipline of INV-LT-8A-02, extended to history). |
| **INV-LT-8.5-07** | **Every snapshot is immutable.** Captured content is never updated; schema evolution creates a NEW representation rather than mutating an existing blob. Ratified in [`LT-8.5-FINAL-GOVERNANCE.md`](./LT-8.5-FINAL-GOVERNANCE.md). |
| **INV-LT-8.5-08** | **Reference identity ≠ blob identity.** `BaselineFingerprint` identifies references; `ContentDigest` identifies immutable blobs; the two are never conflated. Ratified in [`LT-8.5-FINAL-GOVERNANCE.md`](./LT-8.5-FINAL-GOVERNANCE.md). |

---

## 2. Core model (`src/LayoutTranslation/Snapshot/` — design)

| Type | Kind | Role |
|---|---|---|
| `SnapshotReference` | VO | The correlation key — a `BaselineFingerprint` value (`sha256-…`), i.e. the *comparable* (id- and `_origin`-stripped) content address already recorded on baseline/history rows. |
| `SnapshotRole` | enum | `Baseline` (source document at the moment a baseline was established), `Source` (a source-document state), `Target` (a target-draft state). Covers every consumer's needs (§10). |
| `LayoutSnapshot` | VO | `{ document (full, as-captured), reference (fingerprint), contentDigest, role, pageTranslationKey, schemaVersion, byteSize, codec, capturedAt }`. Immutable. The `document` is the **full** layout (ids + `_origin` intact) so it is directly usable by the frozen diff engine and for faithful materialisation. |
| `LayoutSnapshotStoreInterface` | port | `put(document, role, pageTranslationKey): SnapshotReference` (idempotent, content-addressed) · `get(reference, role, pageTranslationKey): ?LayoutSnapshot` · `has(...): bool`. Reads verify integrity (re-hash → fail-closed on mismatch). |
| `SnapshotCaptureInterface` | port | The capture seam (§3) invoked by decorators — `capture(document, role, pageTranslationKey, reference): void`, idempotent. |

**Why the blob stores the full document, addressed by a full-content digest, while the *reference* is the
comparable fingerprint.** The diff engine matches nodes by `_origin` correspondence and strips ids only for
content hashing — so it needs the **full** document (a comparable-form document has no origins and is
useless to it). Deduplication therefore keys the **blob** on a faithful `contentDigest` of the exact bytes,
while the **index** carries the comparable `SnapshotReference` for correlation with baseline/history rows.
Lookup is scoped by `(pageTranslationKey, role, reference)`, so a returned document is guaranteed to
content-address to the recorded baseline fingerprint, with no cross-target ambiguity.

---

## 3. Snapshot lifecycle

```
 Capture ──▶ Reference ──▶ Retain ──▶ (Reclaim)     with Verify on every read
```

1. **Capture** — a snapshot is written at the moments a fingerprint is *already* established, without
   touching frozen code, via **decorators on existing write seams** (bound in a later phase, designed here):
   - a decorator of the LT-7 `BaselineAdvancerInterface` / LT-4 baseline `record()` path captures the
     **source** document that produced the recorded baseline fingerprint as a `Baseline`-role snapshot
     (reading it through the existing source reader; it stores only if `fingerprint(read) == recorded`,
     else fail-soft skip);
   - a decorator of the LT-7 `TargetDraftWriterInterface` captures the post-sync **target** (`T′`) as a
     `Target`-role snapshot (for undo / history diffing).
   Capture is **idempotent** (content-addressed): re-capturing identical content reuses the existing blob.
   A **backfill** console command (never auto-run — LT-4 discipline) captures snapshots for existing
   baselines where the source is still readable; past-gone documents are simply unrecoverable (fail-soft).
2. **Reference** — baseline rows (`fingerprint`) and history rows (`baseline_before` / `baseline_after`)
   *already* hold the reference keys. No schema change to those tables; consumers resolve reference →
   document through the store.
3. **Retain** — snapshots live under the retention policy (§5); referenced snapshots are pinned.
4. **Reclaim** — unpinned snapshots past the retention window are garbage-collected by reference count.
5. **Verify** — every read re-hashes the decompressed document; a mismatch is an integrity fault and the
   read fails closed (returns `null`), never corrupted content (INV-LT-8.5-04).

Capture is a **side plane**: it never gates, delays, or alters a sync (a capture failure is swallowed
fail-soft; the coordinator neither knows nor depends on it — INV-LT-8.5-02).

---

## 4. Storage abstraction

Two layers behind the `LayoutSnapshotStoreInterface` port, so the backend is swappable without any consumer
change:

- **Content store (CAS), additive table `pb_layout_snapshot_blobs`** — `content_digest (unique)`, `codec`,
  `byte_size`, `schema_version`, `payload` (compressed bytes), `refcount`, `created_at`. Immutable,
  write-once; the unique `content_digest` gives **global deduplication**. No FK (content-addressed; the
  source stays independently deletable — the LT-4/LT-7 stance, and the very reason snapshots exist).
- **Snapshot index, additive table `pb_layout_snapshots`** — `id`, `page_translation_key`, `role`,
  `reference` (the comparable `BaselineFingerprint`), `content_digest` (→ blob), `history_id` (nullable),
  `captured_at`; index `(page_translation_key, role, reference)`. Maps a semantic reference to a blob; many
  index rows may share one blob (dedup).

Backend variants (all behind the port): a DB payload column, or a filesystem/object CAS sharded by digest
(`ab/cd/<digest>`), or object storage — chosen by config; consumers are unaware. Adapters return VOs only —
no Eloquent model crosses the port (codebase convention).

Layering: `Snapshot/` is a sibling of `Baseline/`, `Diff/`, `Plan/`, `Execution/`, `Review/`,
`ReviewInfrastructure/`. Dependency direction Translation → Page Builder only; no Core, and nothing under
`Review/` (runtime) or `ReviewUi/` (presentation) is aware of it.

---

## 5. Retention

- **Policy-driven, config** (`page-builder.translation.snapshots.retention`): `mode = all | window | count`,
  `window_days`, `max_per_target`.
- **Pinning by reference (INV-LT-8.5-05).** A snapshot referenced by (a) the current baseline of any target
  or (b) any un-pruned append-only history row (`baseline_before` / `baseline_after`, or a role=Target row
  tied to a `history_id`) is **pinned** and never reclaimed. This guarantees Review and reversibility
  (INV-LT-05) always have their documents.
- **Reclamation by reference count.** Because content is deduplicated, a blob's `refcount` = the number of
  index rows pointing at it; garbage collection removes a blob only when `refcount == 0` **and** it is past
  the window. GC is an explicit console job (never on the hot path).
- **Interaction with append-only history.** History is append-only today (INV-LT-7-EXEC-02); its snapshots
  therefore stay pinned indefinitely. Should a future phase introduce history pruning, dropping a history
  row releases its references, making its now-unreferenced snapshots eligible for GC — the retention model
  needs no change.

---

## 6. Compression / deduplication

- **Deduplication is intrinsic** to content-addressing: identical documents share one `content_digest` blob
  regardless of how many targets/roles reference them. The `contentDigest` is computed over the **exact**
  full-document canonical bytes (distinct from the comparable `BaselineFingerprint`), so two documents that
  differ only in physical ids are stored distinctly (faithful) while byte-identical documents dedup.
- **Compression is a per-blob codec** (`SnapshotCodec` enum: `None`, `Gzip`, `Zstd` …) recorded on the blob;
  `byte_size` is the uncompressed size. Dedup keys on the **uncompressed** digest, so changing the codec
  never defeats deduplication. Codec choice is config-driven, transparent to consumers.
- Blobs are immutable; re-compressing under a new codec writes a new blob only if a migration explicitly
  rewrites it (§7) — normal operation never rewrites a blob.

---

## 7. Migration strategy

- **Additive tables only** (`pb_layout_snapshot_blobs`, `pb_layout_snapshots`), authored **created-not-
  executed** (LT-4 / LT-7 discipline). **No change** to `pb_layout_translation_baselines` or
  `pb_layout_translation_sync_history`.
- **Incremental, no flag-day.** Correctness never depends on a backfill: with no snapshots, consumers fail
  soft (today's behaviour). Backfill and forward-capture only *extend coverage*; there is no migration that
  must complete before the feature is safe.
- **Layout-schema evolution.** Each blob carries `schema_version`. A design-level `SnapshotSchemaMigrator`
  upgrades a captured document to the current layout schema **on read** (lazy, non-destructive) — old
  snapshots stay usable as the layout schema evolves. Blobs are stored as-captured; migration is read-time.
- **Fingerprint-algorithm evolution.** `SnapshotReference` is algorithm-tagged (`sha256-…`, per
  `BaselineFingerprint::ALGO`). A future algorithm gets a new tag; existing references resolve under their
  original tag, so old snapshots remain addressable.

---

## 8. Compatibility

| Surface | Compatibility guarantee |
|---|---|
| **Reference keys** | Snapshot references *are* the `BaselineFingerprint` values already stored on baseline (`fingerprint`) and history (`baseline_before/after`) rows — no new identity to backfill onto existing rows. |
| **Frozen seam** | `BaselineDocumentSourceInterface` is unchanged; the capability supplies a new *implementation* bound in its place (§9). Review Workspace runtime + presentation untouched. |
| **Payload-safety** | Plan / provenance / acceptance / history remain content-free and reference snapshots by fingerprint only (INV-LT-8.5-03). The CAS is a separate content plane those planes point *into*, exactly as commit metadata points at blobs in a content-addressed VCS. |
| **Reversibility** | INV-LT-05 is *strengthened* (document-level restore becomes possible) and never weakened; pinned retention (§5) guarantees the documents history needs. |
| **Deletability** | No FK to `pb_layouts`; the source stays independently deletable/editable — snapshots are self-contained copies, which is *why* the capability is needed. |
| **Dormancy** | Flag-gated with the existing translation flag; unused ⇒ zero behavioural change (fail-soft null). |

---

## 9. Provider seam (plugs underneath, unchanged)

- The frozen **`BaselineDocumentSourceInterface::documentFor(target): ?array`** stays the consumer contract.
- New underneath: **`SnapshotBackedBaselineDocumentSource implements BaselineDocumentSourceInterface`** —
  resolves the target's current baseline `reference` (from the LT-4 baseline row it already reads) and
  returns the `Baseline`-role snapshot document from `LayoutSnapshotStoreInterface`; returns `null` when
  absent (fail-soft) and never returns a document whose re-hash ≠ the reference (fail-closed). Binding it in
  place of `UnavailableBaselineDocumentSource` is a **one-line wiring change** in a later phase — no runtime,
  presentation, coordinator, or diff/planner change to adopt it.
- **Other consumers** read the same store through thin, design-level ports (all reading from the one CAS —
  INV-LT-8.5-06): a `SnapshotResolver` maps semantic references (target + role, `history_id`, or a raw
  `SnapshotReference`) to snapshots for the History Browser / Replay / Undo / Rollback / AI-merge. These are
  seams designed here, not built.

---

## 10. Consumer map

| Consumer | Needs | Snapshot role(s) | Reference source |
|---|---|---|---|
| **Review Workspace** | baseline document B | `Baseline` | baseline row `fingerprint` |
| **History Browser** | before/after documents to render each sync | `Baseline` (before/after) + `Target` | history `baseline_before/after`, `history_id` |
| **Replay** | B, S, T at a historical point | `Baseline` + `Source` + `Target` | history row references |
| **Undo** | the target document *before* a sync | `Target` | history row (pre-sync target) |
| **Rollback** | the document at an earlier baseline | `Baseline` (historical) | history `baseline_before` |
| **AI-assisted merge** | B, S, T | `Baseline` + `Source` + `Target` | current baseline + live reads + history |

Every consumer obtains documents by **reference** from the one store; none re-derives, re-plans, or
re-stores content.

---

## 11. Architecture regression (design-level proof obligations)

The implementation phase must prove:

1. **No frozen contract changed** — `BaselineDocumentSourceInterface`, the coordinator, `Review/` (runtime),
   `ReviewUi/` (presentation), `StructuralDiffEngine`, `SynchronizationPlanner`, the LT-4 baseline
   table/repository, and the LT-7 history table are byte-unchanged. The capability is additive under the
   seam (INV-LT-8.5-02).
2. **Capture edits no frozen code** — snapshots are written only through decorators of existing write seams
   (bound at composition time) and an opt-in backfill; a capture failure never affects a sync.
3. **Metadata payload-safety intact** — a content-token test shows plan / provenance / acceptance / history
   still carry no document content; content is retrievable *only* via the snapshot store, by fingerprint
   (INV-LT-8.5-03).
4. **Content-addressing & integrity** — `put` is idempotent (identical content → identical blob); `get`
   re-hashes and fails closed on mismatch; a returned `Baseline` document content-addresses to the recorded
   baseline fingerprint.
5. **Retention preserves reversibility** — a snapshot referenced by a live baseline or an un-pruned history
   row is never reclaimed (INV-LT-8.5-05); GC removes only `refcount == 0`, past-window blobs.
6. **Dormant & fail-soft** — with the store empty, `SnapshotBackedBaselineDocumentSource` returns `null`
   and the workspace shows the fail-soft blocked state (byte-identical to LT-8D behaviour); no runtime path
   changes while the capability is unused.
7. **No Core change; Translation → Page Builder only.**

---

## Non-goals (LT-8.5)

No implementation, migration execution, or wiring; no UI (History Browser is LT-9); no Replay / Undo /
Rollback / AI-merge logic (each is its own later phase — this only guarantees their inputs); no change to
`BaselineDocumentSourceInterface` or any frozen runtime/coordinator/diff/planner/presentation; no baseline
or history table change; no Core change.

## Freeze

This architecture is the authority for the Baseline Snapshot Capability implementation and for every
consumer that depends on historical documents. The capability **plugs underneath** the frozen seam and may
never change it, weaken metadata payload-safety, or make capture a dependency of the sync path, nor weaken
the eight invariants (INV-LT-8.5-01…08; 07 and 08 ratified in
[`LT-8.5-FINAL-GOVERNANCE.md`](./LT-8.5-FINAL-GOVERNANCE.md)). Amendment path: a future ADR only. **Stop
after architecture.**
