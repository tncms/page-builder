# Design Resource Lifecycle (Phase 26.40C)

> **Type:** Technical Specification (normative). **No production code.**
> Defines the canonical lifecycle of a Design Resource (Step 1) — every state, its meaning,
> and entry/exit conditions. The transition rules (which moves are legal, rollback,
> recovery) are in [design-resource-state-machine.md](design-resource-state-machine.md).
>
> Builds on the frozen contracts of Phase 26.40 / 26.40A / 26.40B. Reuses:
> [design-resource-specification.md](design-resource-specification.md) (RID, C1–C8),
> [design-package-v2.md](design-package-v2.md),
> [design-import-export-specification.md](design-import-export-specification.md),
> [licensing-boundary.md](licensing-boundary.md).
>
> **Conventions:** RFC 2119 keywords. "State" = a named phase of a resource's existence;
> states are invariant KEYS (never localized/renamed).

---

## 1. Two lifecycle scopes

A Design Resource has lifecycle in **two independent scopes**. Conflating them is a
specification error.

| Scope | Owner of state | Question it answers |
|---|---|---|
| **Authoring/Distribution lifecycle** | the publisher / channel | "Where is this resource in its journey from draft to retired?" |
| **Installation lifecycle** | the consuming builder install | "What is the state of *my* copy of this resource?" |

This document defines the **authoring/distribution** lifecycle (Steps 1–2). The
**installation** lifecycle is in
[design-installation-lifecycle.md](design-installation-lifecycle.md). The two meet at
`Published → Installed`.

---

## 2. Lifecycle invariants (normative)

1. **Kind-independent (Rule 1).** `block`, `section`, `page`, and `kit` share the **same**
   lifecycle. There are **no** kind-specific states or transitions.
2. **License-independent (Rule 2).** FREE, Community, Official, and Premium resources share
   the **same** lifecycle. Entitlement changes *who may perform* a transition and *whether a
   consumer may install*, never *which states exist*. Lifecycle state lives in C1–C7 scope;
   entitlement lives in C8 and MUST NOT add or remove states.
3. **Runtime-frozen (Rule 3).** No lifecycle state or transition changes the runtime,
   serialization, CommandBus/BuilderState, or the Extension API.
4. **Identity-stable.** The RID (`<source>:<kind>:<resourceId>`) is constant across the
   entire lifecycle. Version (`X.Y.Z`) advances; the RID does not. A rename is a **new**
   resource with its own lifecycle (see §5).
5. **Deterministic & auditable.** Every transition is deterministic and produces an audit
   record (see [design-audit-traceability.md](design-audit-traceability.md)).

---

## 3. Canonical states (Step 1)

```
 Draft ─▶ Validated ─▶ Packaged ─▶ Signed ─▶ Published ─▶ Installed ─▶ Active
   ▲          │                                   │                       │
   └──────────┘ (fix & revalidate)                ▼                       ▼
                                              Deprecated ───────────▶ Archived
```

| # | State (KEY) | Meaning | Entry condition | Exit condition |
|---|---|---|---|---|
| S0 | `draft` | Authoring in progress (human/AI/hybrid). Mutable. | Resource created | Passes validation → `validated` |
| S1 | `validated` | Conforms to schema + asset/token/compat rules; not yet packaged. | Validation pipeline PASS (no blocking `reasons`) | Packaged, or edited back to `draft` |
| S2 | `packaged` | Serialized into a `tncms.design-package` v2 with `CHECKSUMS`. | Deterministic export produced | Signed, or re-drafted |
| S3 | `signed` | Package signed (or explicitly marked unsigned per channel policy). | Signature applied (seam) or channel allows unsigned | Submitted for publication |
| S4 | `published` | Available in a distribution channel for consumers to install. | Publication pipeline approves + publishes | Deprecated, or unpublished back toward `signed`/`archived` |
| S5 | `installed` | A consumer builder has installed the resource (by reference in the ledger). | Import pipeline commit succeeds | Removed (consumer), or superseded |
| S6 | `active` | Installed **and** usable in the library/insert flow for the consumer. | Installed + entitlement `granted` + compatible with builder | Becomes inactive (entitlement expired / incompatible) or removed |
| S7 | `deprecated` | Publisher marks the resource/version obsolete; still installable/usable but discouraged, often with `supersededBy`. | Publisher deprecates (or channel policy) | Sunset → `archived` |
| S8 | `archived` | Retired from active distribution; no new installs; existing installs grandfathered. | Sunset window elapsed / publisher archives | Terminal (may be restored to `published` only by explicit republish of a new version) |

Notes:
- S0–S4 are **authoring/distribution** states owned by the publisher/channel.
- S5–S6 are the **hand-off** into the installation scope; the authoritative installation
  states (installed/active/inactive/broken/removed/pending-update) are enumerated in
  [design-installation-lifecycle.md](design-installation-lifecycle.md). They appear here only
  to show where the two scopes meet.
- S7–S8 are **retirement** states owned by the publisher/channel; they never break already-
  installed content (grandfathering, §6).

---

## 4. State semantics in detail

### 4.1 `draft` (S0)
- The only **mutable** state. Ids are still absent from the tree (id-free invariant). AI and
  human drafts are indistinguishable in behavior; provenance is recorded in C8
  `generation` (see [design-ai-compatibility.md](design-ai-compatibility.md)).
- A draft has no distribution presence and MUST NOT be installable.

### 4.2 `validated` (S1)
- The resource has passed the full validation pipeline
  ([design-validation-pipeline.md](design-validation-pipeline.md)) with zero blocking
  `reasons` (warnings allowed). Validation is deterministic; re-running on the same input
  yields the same verdict.

### 4.3 `packaged` (S2)
- Deterministic export ([design-import-export-specification.md §B](design-import-export-specification.md))
  produced a canonical package + `CHECKSUMS`. From here the artifact is byte-stable.

### 4.4 `signed` (S3)
- Signature is a **seam** (contract-only). A channel policy decides whether unsigned packages
  may proceed: community → advisory (may proceed unsigned), premium/official → required per
  policy. The state exists regardless of policy so the pipeline is uniform.

### 4.5 `published` (S4)
- The resource version is discoverable in its channel. Discovery **badges** entitlement; it
  never hides premium (licensing-boundary §4). Publishing a **new version** creates a new
  `published` version alongside older ones (version coexistence, see
  [design-update-lifecycle.md](design-update-lifecycle.md)).

### 4.6 `installed` / `active` (S5/S6)
- `installed` = present in the consumer's ledger (`InstalledPack`, references-only, 26.30).
- `active` additionally requires: entitlement `granted` **and** builder-compatible. Loss of
  either moves the consumer copy to an installation-scope inactive/broken state (not an
  authoring-scope state) — defined in
  [design-installation-lifecycle.md](design-installation-lifecycle.md).

### 4.7 `deprecated` (S7)
- A publisher signals obsolescence. The resource remains installable/usable (unless a channel
  additionally suspends it). A `deprecated` resource SHOULD carry `supersededBy: RID`
  (version-compat §7). Deprecation is a **display/advisory** state — it MUST NOT alter runtime
  behavior of installed copies.

### 4.8 `archived` (S8)
- Terminal for that RID/version line. No new installs are offered. Existing installs continue
  to work (grandfathering). Restoration is only via publishing a **new version** (which enters
  at `draft`/`validated`), never by mutating the archived artifact.

---

## 5. Rename, replacement, and identity

- Changing `source`/`kind`/`resourceId` is **not** a lifecycle transition — it produces a new
  RID with its own lifecycle from `draft`.
- To *replace* a resource, publish a new RID and set the old one to `deprecated` with
  `supersededBy` pointing at the new RID, then `archived` after a sunset window.
- To *evolve* a resource, bump `version` and publish a new `published` version of the **same**
  RID (see [design-update-lifecycle.md](design-update-lifecycle.md)).

---

## 6. Grandfathering (normative)

`deprecated` and `archived` affect **distribution**, not **installed content**:
- An installed/active copy remains usable after its source version is deprecated or archived.
- Copy-on-insert means content already placed in documents is fully owned by the user and is
  never revoked by lifecycle changes upstream (design-resource-model §6).
- Entitlement expiry blocks *new* acquisition/updates but grandfathers existing installs
  (licensing-boundary §4.1).

---

## 7. Lifecycle vs distribution channel

The same states apply across every channel (builtin, community, official, premium,
marketplace, cloud, enterprise). Channels differ only in **who** may drive transitions and
**what policy** gates them (review requirements, signing requirements, approval) — see
[design-distribution-model.md](design-distribution-model.md) and
[design-publication-pipeline.md](design-publication-pipeline.md). The state set is invariant.

---

## 8. Conformance checklist

1. All eight authoring/distribution states implemented with the stated entry/exit conditions (§3).
2. Lifecycle identical across kinds and license tiers; entitlement gates actors, not states (§2).
3. RID stable across lifecycle; rename/replacement = new RID (§5).
4. Deprecation/archival never break installed content (grandfathering, §6).
5. Every transition deterministic + audited (§2, audit-traceability doc).
6. No runtime/serialization/Extension-API impact (§2).

---

*End of design-resource-lifecycle.md*
