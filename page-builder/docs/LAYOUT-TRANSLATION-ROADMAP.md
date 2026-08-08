# Layout Translation Roadmap (LT)

**Status: RELEASED ARCHITECTURE — maintenance only (frozen at LT-10).** LT-0 → LT-9D delivered; LT-9X /
LT-9X.1 corrected boundary & scope; **LT-10** froze the architecture. The subsystem is now certified and in
maintenance mode — future work is numbered **LT-M1, LT-M2, …** (not LT-11), governed by
[`LT-10-MAINTENANCE-POLICY.md`](LT-10-MAINTENANCE-POLICY.md). See
[`LT-10-ARCHITECTURE-FREEZE-CERTIFICATE.md`](LT-10-ARCHITECTURE-FREEZE-CERTIFICATE.md).

This roadmap delivers **true multilingual Page Builder layouts**: a translated page begins from a
structural **clone** of the source layout (not a blank document), is then **independently editable**
per locale, and can later be **synchronized** from the source with a baseline-aware three-way
comparison that never destroys target work.

It is a dedicated sub-roadmap **inside** the Page Builder plugin. The parent plugin roadmap is
unchanged. The **Core Translation Platform v1.0 remains frozen**; Page Builder adopts it through
public ports only and **never** builds a parallel translation or locale engine.

> LT-0 (this audit) is complete. See the companion documents:
> - [`LT-0-CORE-ADOPTION-AUDIT.md`](./LT-0-CORE-ADOPTION-AUDIT.md)
> - [`LT-0-INTEGRATION-MATRIX.md`](./LT-0-INTEGRATION-MATRIX.md)
> - [`LT-0-STORAGE-AND-IDENTITY-AUDIT.md`](./LT-0-STORAGE-AND-IDENTITY-AUDIT.md)
> - [`LT-0-SYNCHRONIZATION-ADR.md`](./LT-0-SYNCHRONIZATION-ADR.md)
> - [`LT-0-IMPLEMENTATION-PLAN.md`](./LT-0-IMPLEMENTATION-PLAN.md)

---

## Canonical model

```
Core Content row (cms_contents.id)  ── the translation group anchor (shared content_id)
   │  cms_content_translations(content_id, locale)      ← one text overlay row per locale
   │
   ├─ locale en → Page Builder Layout EN   (independently editable + publishable)
   ├─ locale vi → Page Builder Layout VI   (clone of EN baseline, then diverges)
   └─ locale ja → Page Builder Layout JA
```

A translated layout is a **derivative of a source baseline**, not a permanently locked mirror.

Two identities are required for safe synchronization:

- **`id`** — physical identity inside one layout document (already exists; regenerated on clone).
- **origin correspondence** — stable cross-locale identity, parked in each node's `data` bag
  (plugin-owned, additive; see the ADR). No such concept exists today.

---

## Architectural boundary (unchanged from the brief, confirmed by audit)

**Core owns:** active-locale authority (`cms.language` via `PublicLocaleContextContract`), the
translation relationship (shared `content_id` overlay), translation ports/registries, the Revision
Platform, feature-flag/diagnostics conventions, and Golden-Master discipline.

**Page Builder owns:** translation-aware layout registration through Core ports, clone behavior,
source→target structural correspondence, translation-layout metadata, source baseline tracking,
structural diff, synchronization planning, conflict detection, safe synchronization execution, and
the Builder UI.

**Forbidden:** modifying frozen Core; a second locale registry; a parallel translation engine;
depending on Core internal Posts/Pages adapters; blank translated layouts; overwriting a target by
copying the current source; forcing structural identity; destructive JSON-replacement sync.

---

## Locked phase plan

The audit **validated** the LT-1…LT-10 structure with one recommended combination: **LT-1 and LT-2
merge** because Page Builder does **not** register a Core translation *driver* — it only *consumes*
Core locale read-ports and keys its own storage to the existing `(content_id, locale)` relationship.
See [`LT-0-IMPLEMENTATION-PLAN.md`](./LT-0-IMPLEMENTATION-PLAN.md) for the reasoning and full gates.

| Phase | Title | One-line scope | Hard gate |
|---|---|---|---|
| **LT-0** | Core Adoption & Architecture Audit | This audit. | ✅ No production/schema/runtime/UI change. |
| **LT-1+2** | Locale Adoption & Per-Locale Layout Relationship | Consume Core locale ports; feature flag + diagnostics; make a layout bind to `(owner, locale)` congruent with the Core `(content_id, locale)` translation set. | Existing single-language layouts remain **byte-identical**; Core unchanged. |
| **LT-3** | Clone-from-Source Workflow | "Create Translation" deep-clones the source layout via the canonical mutation pipeline; regenerate physical ids; stamp correspondence; record baseline. | No blank layout; **no source mutation**. |
| **LT-4** | Structural Correspondence & Baseline | Stable cross-locale correspondence + target-owned nodes + immutable source-baseline reference. | Independent target redesign stays supported. |
| **LT-5** | Structural Diff Engine | Read-only 3-way (baseline / source / target) structural diff. | Diff is read-only, deterministic, payload-safe. |
| **LT-6** | Synchronization Plan & Conflict Policy | Turn the diff into an explicit reviewable plan; fail-closed keep-target default. | No target mutation before explicit execution. |
| **LT-7** | Safe Synchronization Execution | Transactional, target-only, protected translated content & target-owned nodes; append-only history. | Full-document replacement is prohibited and tested. |
| **LT-8** | Builder & Review UX | Locale switcher, Create Translation, status, source-changed indicator, Review Changes. | Preview/Publish/Apply/Design-Library UX unchanged outside translation mode. |
| **LT-9** | Revisions, Recovery & Observability | Sync history, restore, diagnostics, failed-sync recovery, no-op detection. | A failed sync cannot leave a partial layout. |
| **LT-10** | Golden-Master Certification & Default Enablement | Parity, clone fidelity, source immutability, target independence, deterministic diff, conflict safety, rollback, locale isolation, perf/query. | No default enablement until all suites green. |

---

## Core-gap decision (from the ADR)

**No Core change is required.** Every capability is either already provided by a **frozen public
Core port** (locale authority, enabled-locale set, the `(content_id, locale)` translation
relationship, optional Revision Platform) or is **plugin-owned and additive** (per-locale layout
binding, node correspondence in `data`, source baseline, diff, plan, execution). See
[`LT-0-SYNCHRONIZATION-ADR.md`](./LT-0-SYNCHRONIZATION-ADR.md) §Core-gap decision.

---

## Out of scope

Machine/AI translation, translation memory, vendor translation APIs, community publishing,
marketplace, commercial licensing, general Core Translation Platform redesign,
taxonomy/menu/theme-option translation, cross-site translation, real-time collaborative translation.
These may consume this foundation later but must not be smuggled into this roadmap.
