# Design Audit & Traceability (Phase 26.40C)

> **Type:** Technical Specification (normative). **No production code.**
> Defines the audit and traceability model (Step 15): what is recorded across the entire
> lifecycle — operation history, publisher, installer, update source, package origin,
> validation history, signature chain, and rollback history.
>
> Consolidates the "audited" requirement asserted by every 26.40C document. Builds on:
> [design-resource-lifecycle.md](design-resource-lifecycle.md),
> [design-resource-state-machine.md](design-resource-state-machine.md),
> [design-publication-pipeline.md](design-publication-pipeline.md),
> [design-installation-lifecycle.md](design-installation-lifecycle.md),
> [design-update-lifecycle.md](design-update-lifecycle.md),
> [design-validation-pipeline.md](design-validation-pipeline.md),
> [design-distribution-model.md](design-distribution-model.md).
>
> **Conventions:** RFC 2119 keywords. Audit is **metadata about operations** — it MUST NOT
> affect runtime, resource identity, or equality.

---

## 1. Principles

1. **Every lifecycle transition and operation is auditable.** Publication, validation,
   install/update/remove/rollback, dependency events, marketplace ops, ownership transfer,
   cloud sync, and policy changes MUST each emit an audit record.
2. **Append-only.** Audit records are immutable and append-only; corrections are new records
   referencing prior ones, never edits.
3. **Deterministic inputs.** Timestamps are **inputs** to operations (not `now()` generated
   inside a deterministic build/export); audit records the supplied timestamps.
4. **No runtime impact.** Audit data is out-of-band; it never changes what renders/inserts and
   is excluded from resource identity/equality (C8-adjacent).
5. **Privacy-aware.** Records use stable actor **KEYS/ids**, not raw PII; AI `promptId` is
   opaque (design-ai-compatibility §2).

---

## 2. Audit record shape

```ts
interface AuditRecord {
  id: string                 // record KEY (monotonic within a scope)
  at: string                 // ISO-8601 (operation input timestamp)
  actor: ActorRef            // who/what performed it
  scope: 'author' | 'channel' | 'install' | 'org' | 'cloud'
  operation: string          // invariant KEY (e.g. "publish", "install", "rollback")
  rid?: string               // affected resource
  fromVersion?: string
  toVersion?: string
  source?: string            // channel/source KEY (origin of the artifact)
  digest?: string            // content-addressed package digest involved
  result: 'ok' | 'blocked' | 'warning'
  reasons?: string[]         // reason KEYS (blocked)
  warnings?: string[]        // warning KEYS
  ref?: string               // prior record id (corrections/chains)
}
interface ActorRef { kind: 'user' | 'ai' | 'system' | 'org'; id: string; model?: string }
```

- `operation` KEYS mirror the events across the lifecycle docs (`validate`, `package`, `sign`,
  `publish`, `deprecate`, `archive`, `install`, `upgrade`, `downgrade`, `repair`, `remove`,
  `rollback`, `transfer`, `verify`, `suspend`, `push`, `pull`, `reconcile`, `policy-change`,
  `approve`, `reject`).
- `result` distinguishes successful, blocked (with `reasons`), and succeeded-with-`warnings`.

---

## 3. Traceability dimensions (Step 15)

Each dimension is answerable from the audit trail + package/manifest metadata.

| Dimension | Recorded via | Answers |
|---|---|---|
| **Operation history** | full `AuditRecord` stream per RID | "What happened to this resource, in order?" |
| **Publisher** | `publish`/`approve` records + manifest `vendor`/`author` + ownership | "Who published/approved this version?" |
| **Installer** | `install` record `actor` + `scope=install`/`org` | "Who installed it, and where?" |
| **Update source** | `upgrade`/`downgrade` record `source` + `fromVersion→toVersion` | "Where did this update come from?" |
| **Package origin** | `source` + `digest` + manifest `metadata.id`/`source` + signature | "Which channel/package produced this artifact?" |
| **Validation history** | `validate` records (`stagesRun`, reasons/warnings, `BuilderEnvironment` version) | "What did validation find, when?" |
| **Signature chain** | signature block + `sign`/`verify` records + key ids | "Who signed it and did verification pass?" |
| **Rollback history** | `rollback` records with `fromVersion→toVersion` + `ref` to the update | "What was rolled back, from/to what, and why?" |

---

## 4. Signature chain (traceability)

- The package signature (seam) binds the canonical manifest + `CHECKSUMS` (one signature over
  all bytes). Audit records each `sign` (author/key) and `verify` (verifier/policy/outcome).
- The chain answers provenance: author key → channel signing policy → consumer verification.
  Offline/community verification failures are recorded as `warning`; premium/enterprise
  required-verification failures as `blocked`.
- Signature verification is deterministic; results are auditable and reproducible.

---

## 5. Validation & rollback history

- **Validation history:** every publication-time and import-time validation run
  (validation-pipeline §5) appends a record with `stagesRun`, `reasons`, `warnings`, artifact
  `digest`, and the `BuilderEnvironment` version — so a past verdict is reproducible and
  explainable.
- **Rollback history:** every rollback (state-machine §4, installation §2.7, update §3) records
  `fromVersion→toVersion`, the triggering operation (`ref`), and outcome. Because prior versions
  are content-addressed and retained, the history is sufficient to reconstruct state.

---

## 6. Scopes & retention

| Scope | Typical retention | Notes |
|---|---|---|
| `author`/`channel` | per channel policy | publication, review, approval, marketplace ops |
| `install` | local install | consumer-side operations |
| `org` | enterprise policy (strict) | approvals, policy changes, pins, managed rollouts |
| `cloud` | provider policy | push/pull/reconcile/conflict resolution |

- Enterprise (org) scope typically mandates the **strictest** retention + separation-of-duties
  evidence (publication §8, enterprise §7).
- Retention/rotation is policy; the record **shape** and determinism are invariant.

---

## 7. Determinism & integrity of the trail

- Records are append-only and reference prior records (`ref`) for chains/corrections.
- The trail MUST be tamper-evident where a channel requires it (e.g. hash-chaining record ids/
  digests) — the mechanism is a policy seam; the requirement to be tamper-evident for
  enterprise/marketplace is normative.
- No audit operation mutates resources or inserted content.

---

## 8. Relationship to other docs

Every 26.40C document asserts "each operation is audited"; **this document is the single
definition** of that audit contract. Where another doc lists an operation (publish, install,
transfer, sync, policy-change, …), the corresponding `AuditRecord` (with the `operation` KEY of
that verb) is REQUIRED.

---

## 9. Conformance checklist

1. Every lifecycle transition/operation emits an append-only `AuditRecord` (§1–§2).
2. All eight traceability dimensions answerable from the trail + manifest (§3).
3. Signature chain and validation/rollback history recorded and reproducible (§4–§5).
4. Timestamps are inputs; records use stable KEYS not raw PII; no runtime impact (§1).
5. Scope-based retention; enterprise strictest; tamper-evident where required (§6–§7).
6. Audit never mutates resources or inserted content (§7).

---

*End of design-audit-traceability.md*
