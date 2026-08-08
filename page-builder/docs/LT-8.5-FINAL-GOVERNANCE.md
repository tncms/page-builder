# LT-8.5 — Final Snapshot Governance (LOCK)

**Status: RATIFIED & LOCKED (LT-8.5). Governance only — no code, no schema, no migration.** Ratifies the
two permanent invariants that, together with **INV-LT-8.5-01…06**
([`LT-8.5-BASELINE-SNAPSHOT-CAPABILITY.md`](./LT-8.5-BASELINE-SNAPSHOT-CAPABILITY.md) §1), govern the
Baseline Snapshot Capability. With this lock the LT-8.5 architecture is **FROZEN**. It may be amended only
by a future ADR, never by an implementation phase.

These two invariants are elevated above the design doc: the capability implementation and every consumer
(Review Workspace, History Browser, Replay, Undo, Rollback, AI-assisted merge) implement *under* them and
may not weaken them.

---

## INV-LT-8.5-07 — Every snapshot is immutable

**Every snapshot is immutable. Captured content is never updated. Schema evolution creates a NEW snapshot
representation rather than mutating an existing one.**

- A snapshot blob is **write-once**: once stored, its bytes are never altered, re-compressed in place, or
  overwritten. Because a blob is addressed by a digest of its exact bytes (INV-LT-8.5-01), mutating it
  would change its address — so mutation is not merely forbidden, it is meaningless.
- **Schema evolution never rewrites a stored snapshot.** Upgrading a captured document to a newer layout
  schema produces a *derived* representation — at read time (lazily, in memory) or, if persisted, as a
  **new** immutable blob with its own `ContentDigest` and `schema_version`. The original blob remains
  untouched and addressable under its original digest.
- Corollary: history is reconstructable *as it happened*. A document read for any past point returns the
  bytes captured then (or a faithful, non-destructive upgrade of them) — never a silently-mutated version.

**Rationale.** Immutability is what makes audit, replay, undo, and rollback trustworthy: the past cannot be
edited under the reader's feet. It also makes deduplication and integrity verification sound — a digest is
a permanent, stable identity only if the bytes it names never change.

## INV-LT-8.5-08 — Reference identity and blob identity are distinct

**Reference identity and blob identity are distinct. `BaselineFingerprint` identifies references.
`ContentDigest` identifies immutable blobs. The two identities must never be conflated.**

- **`BaselineFingerprint` (the reference)** is the *comparable* content address — computed with physical
  ids and `data['_origin']` stripped — and is the value already recorded on baseline and history rows. It
  answers *"which baseline / which point in the pipeline."*
- **`ContentDigest` (the blob)** is the *full-fidelity* digest of a snapshot's exact stored bytes. It
  answers *"which immutable stored document,"* and is the deduplication key of the content store.
- The two live in **different planes** and must never be substituted for one another: a reference is
  resolved to a blob through the snapshot index `(page_translation_key, role, reference) → ContentDigest`,
  never by assuming `reference == ContentDigest`. Two documents may share a `BaselineFingerprint` (identical
  structure/content, different ids) yet be distinct blobs; one document has exactly one `ContentDigest`.
- Corollary: integrity verification re-hashes against the **matching** identity — a blob against its
  `ContentDigest`, and a resolved document's comparable form against the referencing `BaselineFingerprint`.
  A mismatch on either fails closed.

**Rationale.** Conflating the two would silently break either deduplication (if the full-fidelity blob were
keyed by the comparable fingerprint, id-only variants would collide) or correctness (if the comparable
fingerprint were treated as the blob key, the diff engine could receive a document with the wrong origins).
Keeping the identities distinct is what lets the capability be *both* deduplicated *and* faithful.

---

## Scope & precedence

- These invariants bind the LT-8.5 implementation and every consumer of historical documents. An
  implementation may add roles, backends, or codecs; it may **never** mutate a stored snapshot, rewrite a
  blob in place, or conflate reference identity with blob identity.
- They compose with, and never override, **INV-LT-8.5-01…06**, the frozen LT-7 pipeline
  (LT-7-FINAL-GOVERNANCE), the LT-8A/LT-8-PRES workspace governance, and the frozen LT-0-ADR-001
  invariants. Where any document appears to conflict, the frozen ADRs and these locks prevail.
- Amendment path: a future ADR only. No implementation phase, test, or refactor may weaken them.

## Freeze

With this governance ratified, **the Baseline Snapshot Capability architecture is FROZEN.** The LT-8.5
architecture document and the eight invariants (INV-LT-8.5-01…08) are the authority for the capability
implementation and for LT-9 and beyond. Implementation and consumption proceed under them.
