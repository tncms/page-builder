# Design AI Compatibility (Phase 26.40B)

> **Type:** Technical Specification (normative). **No production code.**
> Specifies how **AI-generated** design artifacts (Step 15) — pages, sections, blocks, and
> whole packs — participate in the Design Library on **identical terms** to human-authored
> resources, plus the **additional metadata** AI output MUST carry and the **validation** it
> MUST pass. No new runtime, no new command, no serialization change.
>
> Related: [design-resource-schema.md](design-resource-schema.md),
> [design-package-v2.md](design-package-v2.md),
> [design-import-export-specification.md](design-import-export-specification.md),
> [design-token-specification.md](design-token-specification.md),
> [design-marketplace-metadata.md](design-marketplace-metadata.md).
>
> **Conventions:** RFC 2119 keywords.

---

## 1. Principle — AI output is just a Design Resource

An AI generator (page/section/block/kit or a full package) produces the **same** artifacts
defined by the resource schema and package format. It enters through the **same**
export → validate → import → preview → insert pipeline and is subject to the **same**
compatibility, entitlement, and determinism rules. There is **no special runtime path** and
**no bypass** of validation.

> **Normative rule.** An AI-generated resource that does not conform to
> [design-resource-schema.md](design-resource-schema.md) and
> [design-package-v2.md](design-package-v2.md) MUST be rejected by the same importer as any
> non-conformant human artifact. AI provenance grants no leniency.

---

## 2. Additional metadata for AI-generated artifacts

AI output MUST carry a `generation` descriptor (a namespaced, optional-in-general but
**required-when-AI-authored** block) inside C8/`distribution` (it is provenance, not
runtime):

```ts
interface GenerationMetadata {
  origin: 'ai' | 'human' | 'hybrid'   // REQUIRED when any AI involvement
  model?: string                       // generator model id (display/audit)
  promptId?: string                    // opaque KEY correlating to the request (no raw PII)
  createdAt?: string                   // ISO-8601 (input, not runtime-generated)
  reviewed?: boolean                   // human-reviewed before publish
  reviewer?: string                    // reviewer id (when reviewed)
  confidence?: number                  // 0..1 generator self-score (advisory)
  safety?: {                           // content-safety attestations (advisory)
    checked: boolean
    flags?: string[]                   // invariant KEYS for flagged categories
  }
}
```

Rules:
- `origin` MUST be set truthfully; `hybrid` = AI-assisted human authorship.
- `generation` lives in **C8** → it is **excluded from resource identity/equality** and MUST
  NOT influence runtime (marketplace-metadata §1). Two resources identical in C1–C7 are the
  same resource whether AI- or human-authored.
- `createdAt` is an **input** (determinism); generators MUST NOT emit `now()` into a
  reproducible package (import-export §B).
- No raw prompts or PII in the package; `promptId` is an opaque correlation KEY only.

---

## 3. Validation of AI-generated artifacts

Beyond the standard importer stages (import-export §A.1), AI artifacts MUST pass:

1. **Schema conformance** — full [design-resource-schema.md](design-resource-schema.md)
   validation (structure, id-free, 2-level, one-of `tree`/`members`, JSON-safe).
2. **Asset boundary** — URL-only; no storage ids; no unsafe schemes; no embedded executable
   content (AI MUST NOT invent `data:`/`javascript:` URLs).
3. **Component/feature grounding** — every `type` in the tree MUST be a real registered
   component KEY; unknown types are gated by `requiredComponents` (compatibility §3). AI MUST
   NOT hallucinate component types; if it references one, it MUST declare it in
   `requiredComponents` so the gate blocks cleanly on incompatible builders.
4. **Token discipline** — themeable properties SHOULD use `{ref, fallback}` bindings
   (design-token-spec §1); every binding MUST have a `fallback`. This makes AI output
   theme-portable and prevents hardcoded brand values.
5. **Determinism** — an AI-produced package MUST be canonically serialized and reproducible
   (import-export §B): stripped ids, sorted keys/arrays, no `now()`/randomness leaking into
   the artifact. (The *generation* may be stochastic; the *packaged artifact* MUST be
   deterministic once produced.)
6. **Safety/entitlement** — `generation.safety` is advisory; entitlement/visibility follow
   the standard marketplace rules. AI-authored premium packs are gated identically.

A failure in any class yields the standard blocking `reason` (no AI-specific bypass).

---

## 4. AI at each granularity

| Granularity | Output | Notes |
|---|---|---|
| **Block** | id-free atomic tree | Placed into a target section via `insert-pattern` |
| **Section** | id-free `PatternTree` | Same as human sections |
| **Page** | id-free `TemplateDefinition.tree` | Full Page Template; copy-on-insert; content becomes user's |
| **Pack** | full `tncms.design-package` v2 | Manifest + resources + assets + `generation` metadata; deterministic export |
| **Metadata-only** | tags/category/description suggestions | MAY be applied to existing resources; MUST NOT alter C1–C7 payload/identity |

- **AI-generated pages** are ordinary Full Page Templates (design-resource-model §6):
  import-once-then-own, copy-on-insert, **no synced link** to the generator.
- **AI metadata enrichment** (auto-tagging, descriptions, localization) is allowed but MUST
  stay in display fields / C2 / C8; it MUST NOT change the payload tree or the RID.

---

## 5. Provenance, review & trust

- The library MAY surface an "AI-generated" badge from `generation.origin`, and a
  "human-reviewed" indicator from `generation.reviewed` — **display only**, never a gate.
- Marketplace `verified` (marketplace-metadata §5.1) is independent of AI provenance; a
  marketplace MAY require human review before verifying an AI pack (policy, not schema).
- `generation` is auditable metadata; it MUST NOT be stripped silently on re-export (it is
  provenance) but, being C8, it does not affect the resource digest.

---

## 6. Non-goals

- No AI generation engine, model, or prompt format is specified here (out of scope) — only
  the **compatibility contract** for AI *output*.
- No new runtime/command/serialization surface.
- No leniency in validation for AI artifacts.

---

## 7. Conformance checklist

1. AI output uses the standard resource/package schemas and pipeline; no bypass (§1, §3).
2. `generation` metadata present (`origin` truthful), in C8, excluded from identity/runtime (§2).
3. Component types are real + declared in `requiredComponents`; no hallucinated types (§3.3).
4. Token bindings carry fallbacks; assets URL-only/safe; packaged output deterministic (§3).
5. AI pages are copy-on-insert Full Page Templates; metadata enrichment never mutates payload
   or RID (§4).
6. AI/reviewed badges are display-only; verification/entitlement follow standard rules (§5).

---

*End of design-ai-compatibility.md*
