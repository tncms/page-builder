# ADR-PB-EFFECTIVE-LAYOUT-OWNER-RESOLUTION — One Authoritative Effective Layout Owner

**Status:** Accepted (architecture lock; automated gates GREEN + live EN/VI operator acceptance recorded; frozen at PB-CE-FREEZE-2R)
**Date:** 2026-08-06
**Phase:** PB-ADR-OWNER-1 (owner-resolution audit + consolidation + guard lock)
**Depends on (frozen):** Core Localization Platform, Layout Translation LT-1 / LT-M1 / LT-M1B, ADR-PB-RENDER-SOURCE-001, PB-STATE-2X (Publish request-injection fix).
**Supersedes / amends:** nothing. Hardens and generalizes the invariant proven by PB-STATE-2X.

---

## 1. Status

Accepted. Every automated gate in §17 passes and the live EN/VI browser acceptance
(§16, PB-ADR-OWNER-1 Work O) was executed by the operator: VI Publish targets the VI
effective owner, the VI frontend renders the new VI content, and EN remains unchanged.
Frozen at PB-CE-FREEZE-2R alongside the runtime closure commit.

## 2. Context

The Page Builder edits layouts owned by two different physical entities depending on
locale:

- the **source** (default) locale → the canonical Content owner (`page`, page.id);
- a **non-default** locale → a Core `content_translation` owner (`content_translation`, ContentTranslation.id).

The client always addresses the **canonical** page (`/page/1`); the locale is context
(`?locale=`). The server — never the client — must map *(canonical page + locale)* to
the physical `pb_layouts` owner before any read, write, preview, render, publish,
status projection, or Render Source operation. If any surface infers the owner
independently, EN and VI can diverge.

## 3. Motivating incident (PB-STATE-2X)

`show()` / `publish()` declared `?Request $request = null`. A class-typed parameter
**with a default value is not resolved from the container**, so Laravel injected
`null`; `localeFrom(null)` returned `null`; the resolver fell back to the **source**
owner `page/1`. Result: `POST /publish?locale=vi` published the EN canonical layout,
while `POST /draft?locale=vi` (correct signature) wrote the VI translation. The two
mutations disagreed on the effective owner.

```text
show()/publish()
→ nullable Request defaulted to null
→ real Request not injected
→ locale lost
→ effective owner fell back to source page/1
→ VI publish targeted EN
```

## 4. Terminology

- **Canonical Owner** — the entity routes and permissions address (`page/1`).
- **Effective Layout Owner** — the entity that actually owns the `pb_layouts` row for the requested locale (`page/1` for source, `content_translation/1` for a translation).
- **Layout Identity** — the exact `pb_layouts` row after resolution: `layout_id`, `owner_type`, `owner_id`, resolved locale.
- **Operation Locale** — the locale a read/mutation is requested under.
- **Value Locale** — the locale translatable component values render under (equal to the resolved operation/public locale for editor and public surfaces).

## 5. Decision

> Every locale-aware Page Builder operation resolves the effective layout owner
> through **one** authoritative server-side path — `EffectiveEditorOwnerResolver` —
> from the **canonical** page + a **trusted** operation locale, before reading,
> writing, previewing, rendering, publishing, cloning, or reporting state. No
> endpoint, page, component, renderer, or client infers the effective owner itself.

## 6. Authoritative resolver

`Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver` is the single
resolver. It consumes the frozen `LayoutTranslationResolver` (locale validity, group
identity, source detection, feature dormancy) and maps the descriptor to the physical
owner. It exposes exactly two entry points:

| Method | Nature | On fault | Callers |
|---|---|---|---|
| `resolve(): EffectiveLayoutOwner` | **fail-closed** — editor boot & all mutations | throws `EditorOwnerResolutionException` (4xx) | `EditorContextController::{show,saveDraft,publish}`, `EditorContext::forCanonicalLocale`, `PageBuilder{,Frontend}EditorPage::mount`, `LocaleSwitchProjector`, `RenderSourceWriter::setForCanonical` |
| `resolveForReadOrCanonical(): array{0:string,1:int\|string}` | **fail-soft** — public/preview reads | falls back to the canonical owner (never throws, never writes) | `FrontendRenderer::bodyFor`, `FrontendEditorRenderer::renderInner`, `RenderSourceWriter::currentForCanonical` |

There is no second resolver. LT-internal collaborators (`TargetOwnerResolverInterface`,
`ContentTranslationOwnerResolver`) are inputs *to* this resolver, not alternative
owner-resolution paths.

## 7. Canonical request pipeline

```text
canonical route params  (/page/{id}, ?locale=)
  → permission + canonical-owner guard   (EditableOwners::isSupported / resolve → 403/404)
  → required Request / server-owned context   (Request $request — never optional)
  → locale normalize (localeFrom → trim → null-for-source)
  → EffectiveEditorOwnerResolver::resolve()      [mutations / editor]   ← fail-closed
    or ::resolveForReadOrCanonical()             [public / preview]     ← fail-soft
  → EffectiveLayoutOwner descriptor
  → FirstOpenLayoutProvisioner::ensure()   (editor paths only, idempotent)
  → exact pb_layouts row (LayoutStore::find / saveDraft / publish / setEditorMode)
  → operation
  → response assembled from the SAME row (EditorContext::forResolvedOwner / summarize)
```

No stage substitutes the canonical owner after effective resolution.

## 8. Mutation vs read fallback

- **Mutations fail closed.** `saveDraft`, `publish`, both `setMode` header actions, and
  `RenderSourceWriter::setForCanonical` resolve via `resolve()`. An invalid / disabled /
  out-of-group / absent-translation locale throws — it never silently retargets the
  source owner.
- **Public reads fail soft.** `FrontendRenderer` and `FrontendEditorRenderer` resolve via
  `resolveForReadOrCanonical()`: a bad locale renders the canonical layout, and render
  **never** clones, provisions, or writes.

## 9. Endpoint parity (canonical `page/1`, `locale=vi`)

`show`, `saveDraft`, `publish`, the frontend-editor iframe preview, public render, and
Render Source all resolve:

```text
effective owner_type = content_translation
effective owner_id   = the VI ContentTranslation id
layout_id            = the same VI pb_layouts row across every operation
resolved locale      = vi
```

Proven at the real route-dispatch layer by
`PageBuilderEffectiveOwnerResolutionTest` (L1–L12).

## 10. Response identity

A mutation response is built from the exact row it operated on
(`EditorContext::summarize($layout)` where `$layout` is the row returned by
`LayoutStore::{saveDraft,publish}` for the effective owner; `show` uses
`forResolvedOwner`, which reads the row at the resolved owner and exposes `layout_id`).
A translation mutation response therefore never returns the source row — verified with
distinct EN/VI sentinels.

## 11. Publication-state ownership

`is_published_current`, and the client's `Published` / `Unpublished changes` /
`Publish failed` states, are computed on the effective owner's row (draft vs published).
VI diverging its draft after publish does not change EN's status, and vice versa (L7).

## 12. Render Source ownership

`RenderSourceWriter` reads/writes the Render Source (stored as `pb_layouts.editor_mode`
per ADR-PB-RENDER-SOURCE-001) on the **effective** owner. The **write** path is
fail-closed (§8); the **read** path is fail-soft. Setting EN's source never touches VI's
and vice versa (L8).

## 13. Locale switch rule

`LocaleSwitchProjector` builds each locale's editor URL from the **canonical** owner id +
locale (`EditorUrls::live($canonicalType, $canonicalId, null, $locale)`). Switch URLs
vary only by locale and never carry an effective-owner id. EN→VI→EN round-trips return
to the correct owner each time (L11).

## 14. Client trust boundary

The client sends only canonical route params + a locale hint. The server re-resolves the
owner on every operation. Forged body fields (`owner_type`, `owner_id`,
`layout_owner_id`) are ignored — proven by the architecture guard.

## 15. Core / plugin ownership

Locale authority is consumed from Core contracts (`LanguageConfigurationContract`,
`PublicLocaleContextContract`); owner/layout knowledge lives entirely in the plugin. No
Core file references the Page Builder — guarded by a Core-independence scan.

## 16. Failure behavior

| Condition | Editor / mutation (fail-closed) | Public / preview read (fail-soft) |
|---|---|---|
| Source locale | canonical owner | canonical owner |
| Translation exists | translation owner | translation owner |
| Translation unpublished | (editor still edits its draft) | classic body of the resolved owner |
| Invalid / disabled / forged locale | `EditorOwnerResolutionException` → 4xx | canonical owner, no error |
| Missing mandatory Request | impossible — `Request` is non-optional | n/a |
| Malformed builder payload | n/a | Classic fallback (never 500) |

No mutation path may use the fail-soft method.

## 17. Test requirements

- **Route-dispatch parity** (`PageBuilderEffectiveOwnerResolutionTest`, L1–L12): real `ControllerDispatcher`, EN/VI parity, same VI `layout_id`, distinct sentinels, VI publish isolates EN, owner-scoped status + Render Source, invalid-locale fail-closed, request-injection guard, EN→VI→EN round trip, data-driven owner type.
- **Architecture guards** (`PageBuilderOwnerResolutionArchitectureGuardTest`): non-optional Request, two-method resolver contract, editor mutations use only fail-closed resolution, Render Source write fails closed, public read is fail-soft, no client-owner trust, Core-independence.
- Companion coverage retained: `PageBuilderPublishRequestInjectionTest`, `PageBuilderLocaleAwareRuntimeTest`, `PageRenderSourceTest`, `PageBuilderWholeLayoutPublishTest`, `PageBuilderPublicationStateTest`.

## 18. Architecture guards

See §17. Guards are Reflection + behaviour first; the one structural source scan
(`EditorContextController` uses `resolve()` exactly three times and never
`resolveForReadOrCanonical`) backs the fail-closed-mutation invariant behaviourally.

## 19. Consequences

- The class of defect PB-STATE-2X fixed cannot silently reappear: a locale-aware route
  action can no longer regress to an optional/default `Request`, and a mutation can no
  longer resolve fail-soft.
- One documented correction was required: `RenderSourceWriter::setForCanonical` was a
  mutation calling the fail-soft resolver; it is now fail-closed, with the Filament
  Rendering control surfacing a message instead of writing the wrong owner.
- Post/CPT, templates, reusable sections, and future surfaces inherit the pipeline by
  construction — they resolve through the same resolver or they fail the guards.

## 20. Rejected alternatives

See §21 (Forbidden patterns) — each rejected alternative is codified as a forbidden
pattern with a guard.

## 21. Forbidden patterns

- Client sends the effective owner id (guarded: forged body fields ignored).
- Each endpoint resolves the owner independently / a second resolver.
- `?Request $request = null` on a locale-aware route action (guarded: Reflection).
- `request()` used as a hidden locale fallback inside a mutation.
- A mutation falling back to the canonical owner on a bad locale (guarded: fail-closed).
- One ambiguous resolver method serving both read and mutation.
- Per-component / per-surface owner resolution.
- Owner/layout knowledge moved into Core (guarded: Core-independence scan).
- Per-element publish lifecycle.

## 22. Post/CPT extension rules

Adding Post/CPT (or any new builder owner) MUST:

1. register the owner type in `EditableOwners` only;
2. reuse `EffectiveEditorOwnerResolver` — never add a second resolver;
3. keep canonical route identity (`/{ownerType}/{ownerId}` + `?locale=`) with no
   effective-owner id in the URL or client payload;
4. keep mutations fail-closed and public reads fail-soft;
5. extend `PageBuilderEffectiveOwnerResolutionTest::canonicalOwnerTypes()` — the L12
   data-driven assertion then covers the new type automatically.

No Post/CPT support is authorized by this ADR.

## 23. Compatibility notes

- No Core change, no schema/migration, no new owner table.
- Feature-flag-off (translation dormant) preserves legacy single-language behaviour: every
  locale resolves the single shared (source) layout.
- Render Source continues to persist through `pb_layouts.editor_mode` (ADR-PB-RENDER-SOURCE-001).

## 24. Acceptance checklist

- [x] One authoritative resolver; no second resolver.
- [x] All locale-aware route actions require a non-optional `Request`.
- [x] Show / Draft / Publish / Preview VI parity: same effective owner + `layout_id`.
- [x] Mutations fail closed (incl. Render Source write — corrected this phase).
- [x] Public read fallback explicit, isolated, never writes.
- [x] Response identity matches operation identity.
- [x] Publication status owner-scoped.
- [x] Render Source owner-scoped.
- [x] Locale switch preserves canonical route identity; no effective-owner id in URLs.
- [x] No client effective-owner trust.
- [x] No Core / schema / migration change.
- [x] Permanent route-dispatch + architecture guards in place and green.
- [ ] Live EN/VI browser acceptance (§16 Work O) — pending a booted admin.

Flip Status to **Accepted** once the final box is checked.
