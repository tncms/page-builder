# Design Validation Pipeline (Phase 26.40C)

> **Type:** Technical Specification (normative). **No production code.**
> Defines the end-to-end validation pipeline (Step 7) applied at publication and at
> import/install, and the **AI content lifecycle/validation** rules (Step 11). Consolidates
> the validation stages referenced across 26.40B into one ordered, deterministic contract.
>
> Builds on: [design-import-export-specification.md](design-import-export-specification.md)
> (import stages), [design-resource-schema.md](design-resource-schema.md),
> [design-version-compatibility.md](design-version-compatibility.md),
> [design-token-specification.md](design-token-specification.md),
> [design-ai-compatibility.md](design-ai-compatibility.md),
> [design-publication-pipeline.md](design-publication-pipeline.md).
>
> **Conventions:** RFC 2119 keywords. Validation yields blocking `reasons` (KEY + message) vs
> non-blocking `warnings`, reusing the 26.27 result shape.

---

## 1. Where validation runs

The **same** pipeline runs at two points, with the same stages and the same determinism:

| Point | Purpose | Failure effect |
|---|---|---|
| **Publication** (author side) | Gate `draft → validated` before packaging | Cannot package/publish; returns to `draft` |
| **Import/Install** (consumer side) | Gate before atomic commit | Nothing installed; library unchanged |

Validation is **read-only** and side-effect-free (no library mutation, no network in core).

---

## 2. Ordered stages (normative)

Fail-fast on the first stage that produces a blocking `reason` for a given artifact; collect
warnings across stages.

| # | Stage (KEY) | Checks | Blocking vs warning |
|---|---|---|---|
| V1 | `integrity` | `CHECKSUMS` digests match; file set matches manifest | mismatch → **reason** `integrity.digest_mismatch` |
| V2 | `signature` | signature verifies per channel trust policy (seam) | community → **warning**; premium/official (policy=required) → **reason** |
| V3 | `structural` | resource + manifest schema conformance; one-of `tree`/`members`; id-free; 2-level; JSON-safe | violation → **reason** |
| V4 | `asset-boundary` | URL-only; no storage/attachment ids; safe schemes (isSafeAssetUrl); no `data:`/`javascript:` | violation → **reason** `asset.*` |
| V5 | `token` | every `TokenBinding` has a `fallback`; refs use closed categories | missing fallback → **reason**; unresolved ref in theme → **warning** |
| V6 | `compatibility` | `PackageCompatibility` + per-resource C7 vs `BuilderEnvironment` | missing known component/feature or builder-out-of-range → **reason**; unverifiable → **warning** |
| V7 | `dependency` | resolve graph; ranges; cycles | missing/out-of-range required or cycle → **reason**; optional missing → **warning** |
| V8 | `security` | no executable/script payload; asset MIME sanity; size bounds; manifest well-formed | violation → **reason** |
| V9 | `provenance` (AI) | `generation` present + truthful when AI-authored; component grounding | missing/false provenance or hallucinated component → **reason** (§4) |

Stages V1–V2 are integrity/trust; V3–V5 are structure/portability; V6–V7 are
compatibility/deps; V8–V9 are safety/provenance. Ordering is normative so that cheap,
foundational checks fail fast.

---

## 3. Result model

```ts
interface ValidationResult {
  ok: boolean
  reasons: ValidationIssue[]   // blocking; present iff !ok
  warnings: ValidationIssue[]  // non-blocking
  stagesRun: string[]          // for audit
}
interface ValidationIssue { code: string; message: string; stage: string; rid?: string; path?: string }
```

- `ok = reasons.length === 0`. Warnings never set `ok = false`.
- `code` is an invariant KEY; `message` is localizable display text.
- The result is **deterministic**: same artifact + same `BuilderEnvironment`/policy snapshot →
  identical `reasons`/`warnings` (ordering stable).

---

## 4. AI content lifecycle & validation (Step 11)

AI-generated artifacts (`generation.origin ∈ {ai, hybrid}`) follow the **identical**
lifecycle and validation with **no bypass** (design-ai-compatibility §1).

### 4.1 AI lifecycle
- AI output enters at `draft` like any resource; it MUST pass V1–V9 to reach `validated`.
- It MUST NOT skip publication **review** on any channel that requires review
  (publication-pipeline §5); a channel MUST NOT auto-approve because content is AI-generated.
- Provenance (`origin`, `model`, `promptId`, `reviewed`, `reviewer`) is recorded in C8 and in
  the audit trail; it is display metadata, never a gate substitute.

### 4.2 AI-specific validation (stage V9)
- **Provenance truthfulness:** `generation.origin` MUST be set; hybrid = AI-assisted human.
- **Component grounding:** every `type` in the tree MUST be a registered component KEY; any
  referenced component MUST be declared in `requiredComponents` so the compat gate (V6) blocks
  cleanly on incompatible builders. Hallucinated/unknown types → blocking `reason`
  `ai.unknown_component`.
- **No smuggled payloads:** AI MUST NOT emit `data:`/`javascript:` URLs or executable content
  (caught by V4/V8); provenance grants no exemption.
- **Determinism of artifact:** the *generation* may be stochastic, but the *packaged artifact*
  MUST be canonical/reproducible (import-export §B). A non-deterministic package → blocking
  `reason`.

### 4.3 Review interaction
- AI artifacts on review-required channels are reviewed **before** approval; `generation.reviewed`
  reflects that a human reviewed it. An AI badge MAY be surfaced (display only).

---

## 5. Determinism & audit

- The pipeline performs no network calls in core, uses no wall-clock/randomness, and evaluates
  guards against a snapshot → fully reproducible verdicts.
- Every validation run (at publication and at import) is audited: stages run, reasons/warnings,
  outcome, artifact digest, `BuilderEnvironment` version — per
  [design-audit-traceability.md](design-audit-traceability.md).

---

## 6. Relationship to import/publish

- At **import/install**, V1–V9 are the read-only gates preceding the atomic commit
  (import-export §A.1); a blocking `reason` aborts with the library unchanged.
- At **publication**, V1 (integrity over the just-built package) + V3–V9 gate
  `draft → validated → packaged`; V2 (signature) applies at/after signing per channel policy.
- The stage set and semantics are identical at both points; only the trigger differs.

---

## 7. Conformance checklist

1. Same ordered, deterministic pipeline at publication and import; read-only, no core network (§1–§2, §5).
2. Blocking `reasons` vs non-blocking `warnings` per 26.27 shape; `ok` iff no reasons (§3).
3. Integrity/signature/structural/asset/token/compat/dependency/security/provenance all covered (§2).
4. AI artifacts validated identically with no bypass; provenance truthful; components grounded (§4).
5. AI never auto-approved; review required where the channel requires it (§4.3, publication §5).
6. Every run audited (§5).

---

*End of design-validation-pipeline.md*
