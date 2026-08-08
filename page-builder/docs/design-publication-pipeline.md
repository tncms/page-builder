# Design Publication Pipeline (Phase 26.40C)

> **Type:** Technical Specification (normative). **No production code.**
> Defines how a Design Resource/package moves from a designer to a distribution channel
> (Step 4): Designer → Validation → Packaging → Signing → Review → Approval → Publish →
> Distribution. Covers official, community, commercial, and private paths, and the rule that
> **AI-generated content never bypasses review**.
>
> Builds on: [design-resource-lifecycle.md](design-resource-lifecycle.md),
> [design-resource-state-machine.md](design-resource-state-machine.md),
> [design-validation-pipeline.md](design-validation-pipeline.md),
> [design-import-export-specification.md](design-import-export-specification.md),
> [design-distribution-model.md](design-distribution-model.md),
> [design-marketplace-metadata.md](design-marketplace-metadata.md).
>
> **Conventions:** RFC 2119 keywords. Stage KEYS are invariant.

---

## 1. Pipeline overview

```
[author]                                                            [channel]
Designer ─▶ Validation ─▶ Packaging ─▶ Signing ─▶ Review ─▶ Approval ─▶ Publish ─▶ Distribution
  (draft)   (validated)   (packaged)   (signed)   (policy)  (policy)   (published)   (channel)
```

Each stage maps to a lifecycle state/transition
([design-resource-lifecycle.md](design-resource-lifecycle.md)). The pipeline is **uniform**
across channels; channels differ only in **which policy gates apply** (§4). The pipeline
MUST be deterministic and fully audited.

---

## 2. Stages

### 2.1 Designer (→ `draft`)
- Authoring by a human, an AI generator, or hybrid. Output is an id-free resource + metadata
  (C1–C8). Provenance recorded in C8 `generation` (`origin: ai|human|hybrid`).
- No distribution presence; not installable.

### 2.2 Validation (→ `validated`)
- Full validation pipeline ([design-validation-pipeline.md](design-validation-pipeline.md)):
  schema → asset boundary → token discipline → compatibility → security → (component
  grounding for AI). Zero blocking `reasons` required to pass; warnings allowed.
- Deterministic: same input → same verdict.

### 2.3 Packaging (→ `packaged`)
- Deterministic export ([design-import-export-specification.md §B](design-import-export-specification.md)):
  canonical serialization, stripped ids, content-addressed assets, `CHECKSUMS`.

### 2.4 Signing (→ `signed`)
- Signature is a **seam** (contract-only). Whether unsigned may proceed is a **channel
  policy** (§4). Signing binds the canonical manifest + checksum index (one signature protects
  all bytes).

### 2.5 Review (policy stage)
- A **human/policy review** of the packaged, signed artifact. Review outcome ∈
  `{approved, rejected, changes-requested}`. Rejection returns to `draft` with reasons;
  changes-requested is equivalent for pipeline purposes.
- **AI content never bypasses review** (§5). Review requirement is per channel (§4).

### 2.6 Approval (policy stage)
- Formal `G-APPROVED` grant recorded against the artifact version + reviewer identity
  (audit). Approval is distinct from review to allow separation of duties (reviewer ≠
  approver) in stricter channels/enterprise.

### 2.7 Publish (→ `published`)
- The artifact version becomes discoverable in its channel. Guards: `G-SIGN ∧ G-APPROVED ∧
  G-PERM` (state-machine T7). Publishing a new version coexists with prior published versions.

### 2.8 Distribution
- The channel makes the published version available to consumers per its distribution model
  ([design-distribution-model.md](design-distribution-model.md)); discovery **badges**
  entitlement, never hides premium.

---

## 3. Determinism, atomicity, idempotence

- Each stage is a pure function of its input artifact + policy snapshot; re-running a stage on
  the same input yields the same result.
- Publish is **atomic**: either the version becomes fully discoverable or not at all.
- Re-publishing an identical artifact version (same digest) is an idempotent no-op (audited).

---

## 4. Channel policy profiles

The pipeline is identical; these gates toggle per channel. Rows are policy requirements.

| Policy gate | Builtin/Official | Community | Commercial/Marketplace | Private/Personal | Enterprise |
|---|---|---|---|---|---|
| Validation (2.2) | **required** | **required** | **required** | **required** | **required** |
| Deterministic packaging (2.3) | required | required | required | required | required |
| Signing (2.4) | **required** | advisory (recommended) | **required** | optional | **required** |
| Review (2.5) | **required** | required (community/maintainer) | **required** | optional (self) | **required** (internal) |
| Approval (2.6) | required | maintainer approval | required (marketplace) | self-approval | required (admin/policy) |
| Entitlement setup (C8) | free/bundled | free | pricing/trial | n/a | policy-defined |

Normative: **Validation and deterministic packaging are required on every channel.** Signing,
review, and approval strictness scale by trust. No channel may skip validation, and no channel
may publish content that failed validation.

---

## 5. AI content never bypasses review (normative)

- AI-generated (`generation.origin ∈ {ai, hybrid}`) artifacts traverse the **same** pipeline
  with **no leniency and no skipped stages** (design-ai-compatibility §1).
- On any channel that requires review (§4), AI artifacts MUST be reviewed **before** approval;
  a channel MUST NOT auto-approve solely because content is AI-generated.
- Provenance (`origin`, `model`, `reviewed`, `reviewer`) is recorded in the audit trail and
  MAY be surfaced as a display badge — display only, never a gate that substitutes for review.
- AI provenance grants no fast-path and no exemption from signing/validation.

---

## 6. Rejection & resubmission

- A rejected artifact returns to `draft` (state-machine T2/T4) with structured `reasons`
  (KEY + message). Fixing and resubmitting re-enters at Validation.
- Rejection is non-mutating to any published version; it only affects the candidate.
- Every rejection/resubmission is audited (publisher, reviewer, reason, timestamp-as-input).

---

## 7. Versioned publication

- Publishing produces a `published` **version** of the RID. Multiple versions of one RID may
  be `published` simultaneously (consumers resolve per range / pin — see
  [design-update-lifecycle.md](design-update-lifecycle.md)).
- Deprecation/sunset of a published version follows the lifecycle (deprecate → archive) and
  never breaks installed consumers (grandfathering).

---

## 8. Ownership & permissions

- Only actors authorized for the resource's **ownership** and target **channel** may drive
  publication (`G-PERM`) — ownership types and transfer are defined in
  [design-distribution-model.md](design-distribution-model.md).
- Separation of duties (author ≠ reviewer ≠ approver) is REQUIRED on official/commercial/
  enterprise channels and OPTIONAL on private/personal.

---

## 9. Conformance checklist

1. All eight stages implemented, mapped to lifecycle states; uniform across channels (§1–§2).
2. Validation + deterministic packaging required on every channel; failed validation never
   publishes (§4).
3. Signing/review/approval strictness scales per channel policy profile (§4).
4. AI content is reviewed like any other — no bypass, no auto-approve, provenance audited (§5).
5. Publish is atomic; identical re-publish idempotent; rejection non-mutating + audited (§3, §6).
6. Only authorized actors publish; separation of duties on high-trust channels (§8).

---

*End of design-publication-pipeline.md*
