# LT-0 — Implementation Plan (Locked Downstream Roadmap)

**Status: AUDIT (LT-0). No production code, schema, runtime, or UI was changed.**

The locked, gated plan for LT-1…LT-10, derived from the audit and governed by
[`LT-0-SYNCHRONIZATION-ADR.md`](./LT-0-SYNCHRONIZATION-ADR.md). Each phase lists deliverables, the
exact existing seams it reuses, its hard gate, and its verification.

---

## Roadmap validation & combination recommendation

The planned phases are **valid**. One combination is recommended:

- **Merge LT-1 (Registration Foundation) into LT-2.** Rationale: Page Builder does **not** register a
  Core translation *driver* or `LocalizedContentContract` — layout text localization already works
  (`LocalizedValue`/`Localize::text()`), and the layout is plugin storage, not a Core-localized field
  entity. LT-1's real content is thin: *consume* Core locale read-ports, add a feature flag (default
  off), and add a never-throwing diagnostics report. That belongs with the per-locale binding it
  enables. Combining avoids a micro-phase with no registration to perform.

All other phases stay distinct. In particular **LT-5 (read-only diff)** and **LT-6 (plan/policy)** are
**not** merged: keeping the read-only diff behind its own gate is the primary safety boundary before
any decision logic exists.

---

## Phases

### LT-1+2 — Locale Adoption & Per-Locale Layout Relationship
**Reuse:** `PublicLocaleContextContract`, `LanguageConfigurationContract`, `Content` `(content_id,
locale)`; `LayoutStore`.
**Deliverables:** plugin config flag `page-builder.translation.enabled` (default **false**); never-throwing
diagnostics report; decide binding **Option A** (`locale` column, unique `(owner_type, owner_id,
locale)`) vs **Option B** (`pb_layout_translations` table) per
[`LT-0-STORAGE-AND-IDENTITY-AUDIT.md`](./LT-0-STORAGE-AND-IDENTITY-AUDIT.md) §6; additive migration;
`LayoutStore` gains locale-aware read/write overloads that default to the default locale; backfill the
existing row as default-locale.
**Gate:** flag off ⇒ existing single-language layouts **byte-identical**; Core unchanged; migration
reversible.
**Verify:** existing Page Builder + Core translation suites green; new tests for default-locale
byte-identity and locale-keyed CRUD.

### LT-3 — Clone-from-Source Workflow
**Reuse:** `FullPageImportService::import()` transactional shape; `LayoutNormalizer`;
`NodeIdGenerator::assign`; `LayoutStore::saveDraft`; `EditorLauncherPanel` UX hook.
**Deliverables:** "Create Translation" action; clone service (source immutable → normalize → fresh
physical ids → stamp origin correspondence in `data` → `saveDraft(target draft)`); record source
baseline (LT-4 primitive); open Builder in target locale.
**Gate:** no blank layout; **no source mutation** (asserted in tests).

### LT-4 — Structural Correspondence & Baseline
**Reuse:** `data` passthrough for correspondence; `DesignFingerprint`/`DesignPackageManifest::
canonicalJson` for baseline hashing.
**Deliverables:** stable cross-locale correspondence id in `data['_origin']`; target-owned node marker;
immutable source-baseline reference (fingerprint over id-free, origin-free canonical payload +
last-synced source reference); integrity/orphan diagnostics.
**Gate:** independent target redesign stays supported; correspondence survives id regeneration
(verified in [`LT-0-STORAGE-AND-IDENTITY-AUDIT.md`](./LT-0-STORAGE-AND-IDENTITY-AUDIT.md) §3).

### LT-5 — Structural Diff Engine
**Deliverables:** deterministic, **read-only** 3-way diff (`B`/`S`/`T`) producing the canonical change
model (D8); node matching by correspondence; property-level diff; move/hierarchy detection; target
customization detection.
**Gate:** no mutation; deterministic; payload-safe (no runtime/media resolution).

### LT-6 — Synchronization Plan & Conflict Policy
**Deliverables:** reviewable plan from the diff; safe/review/conflict classification; canonical conflict
reasons; **fail-closed keep-target** default; selective acceptance; ignored-change tracking; plan preview.
**Gate:** no target mutation before explicit execution.

### LT-7 — Safe Synchronization Execution
**Reuse:** `DB::transaction` + `LayoutStore::saveDraft` (target only).
**Deliverables:** transactional apply of accepted items; source immutability; protected translated
content and target-owned nodes; baseline advanced on success; append-only synchronization history;
rollback/recovery.
**Gate:** full-document replacement **prohibited and tested** (INV-LT-02).

### LT-8 — Builder & Review UX
**Reuse:** core `HasTranslations`/`TranslationSwitcher` pattern; Ecommerce
`translationStatusIndicator` + lifecycle pattern; `EditorLauncherPanel`; `EditorUrls`;
`PageBuilderEditorPage::mount`.
**Deliverables:** locale switcher; Create Translation; translation status; source-changed indicator;
Review Changes screen (new deep-link page); Keep Target / Accept Source / Ignore; open target locale;
localized UI strings.
**Gate:** Preview/Publish/Apply/Design-Library/editing UX unchanged outside translation mode.

### LT-9 — Revisions, Recovery & Observability
**Deliverables:** synchronization history; restore/recovery; diagnostics; audit events (plugin-owned
"layout published/source-changed" observer on `LayoutStore` writes); failed-sync recovery; no-op
detection; operational docs. Optional: opt layout into `cms.revision` for editor history (orthogonal).
**Gate:** a failed synchronization cannot leave a partial layout (INV-LT-05).

### LT-10 — Golden-Master Certification & Default Enablement
**Deliverables:** legacy parity; clone fidelity; source immutability; target independence; deterministic
diff; conflict safety; sync rollback; locale isolation; performance/query checks (no N+1 in frontend
resolution); feature-flag rollback; certification report; default-enablement decision.
**Gate:** **no default enablement** until all certification suites are green.

---

## Cross-cutting decisions to finalize early

1. **Frontend fallback policy** (LT-2/LT-3): `FrontendRenderer::bodyFor` becomes locale-aware —
   target-locale published layout → else default-locale published layout → else classic body. Must be
   **explicit and documented**, replacing today's locale-blind lookup
   (`FrontendRenderer.php:37-77`). Do not change current 404/draft behavior without a decision record.
2. **Binding option A vs B** (LT-2): pick before any migration; default recommendation is Option A
   (`locale` column) for the smallest surface, with backfill.
3. **Baseline canonicalization** (LT-4): hash over id-free **and** origin-free canonical JSON.

---

## Test plan (downstream; none executed in LT-0)

- **Unit:** correspondence stamping/survival across `NodeIdGenerator`/`ComponentId`; baseline
  canonicalization determinism; diff determinism; conflict classification; plan generation.
- **Integration:** clone via `FullPageImportService` pattern (source unchanged); locale-keyed
  `LayoutStore` CRUD; transactional sync apply + rollback; frontend per-locale resolution + fallback.
- **Golden-Master (LT-10):** default-locale byte-identity with flag off; clone fidelity; source
  immutability; target independence; non-destructive sync; locale isolation; query budget on frontend
  resolution; feature-flag rollback.
- **Coverage:** meet the repo's PHPUnit standard for all new plugin services; diagnostics reports
  asserted never-throwing.

---

## LT-0 commands executed (audit, non-mutating)

- `Glob` — enumerate plugins, Core translation/localization source, plugin migrations/docs.
- `Grep` — locale/translation references in the plugin; owner/layout resolution; node-identity concepts.
- `Read` — `pb_layouts` migration; `PageBuilderLayout`, `LayoutStore`, `EditorContext`,
  `EditableOwners`, `ContentTranslation` (+ its migration), `NodeIdGenerator`, `DesignFingerprint`,
  `ComponentId`.
- Five parallel read-only source-tracing passes (Core translation surface; PB storage/identity; PB
  mutation pipeline; page/locale/layout + frontend resolution; UX integration).

**No test suite was run:** LT-0 mandates no production/test change and the architectural facts are
established by source (schema, constraints, and pipeline are proven by reading, not execution). Targeted
Page Builder / Core translation suites can be run on request to establish a pre-LT-1 green baseline.

**Confirmation:** Core, database schema, runtime behavior, and UI are **unchanged**. Only the six LT-0
Markdown documents in `plugins/page-builder/docs/` were created.
