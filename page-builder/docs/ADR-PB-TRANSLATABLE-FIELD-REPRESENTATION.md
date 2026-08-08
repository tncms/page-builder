# ADR-PB-TRANSLATABLE-FIELD-REPRESENTATION

Status: ACCEPTED (PB-STATE-2T). Scope: **Page Builder component-data policy only** — not a shared Core Translation Platform.

## Context

A translatable Page Builder field (`TextField/TextareaField->translatable()`) is stored in component `data`. Across iterations three defects compounded:

1. Editor render endpoints resolved the effective **owner** by the editing locale but rendered **values** under the ambient admin `app()->getLocale()` (= source default), so a component's fields resolved in mixed locales.
2. `Localize::text()` fell back to the **first non-empty map entry by array order**, silently mixing languages per field.
3. Payloads hold mixed shapes (plain scalar, `{"vi":…}`, `{"en":…,"vi":…}`) with no single interpretation.

## Decision

### Canonical representation (INV-PB-L10N-03)
A translatable field is a **per-locale envelope** `array<string locale, string value>`. A plain **scalar is legacy** = the source-locale text, interpreted (never rewritten) at the read boundary. A `content_translation` layout stores owner-locale values; a **target-only** envelope (`{"vi":…}`) is canonical for a translation owner. Extra source keys (`{"en":…,"vi":…}`) are permitted and harmless — neither required nor stripped. Writes already produce envelopes (inspector/inline `{...base, [locale]: value}`); no write change is needed.

### One render value-locale authority (INV-PB-L10N-01/02)
Every render resolves envelopes under **one explicit locale**:
- **Public** surfaces get it from the request URL prefix (`ApplyPublicLocale` → `app()->getLocale()`).
- **Editor** surfaces scope it via `Support\RenderLocale::scope($editingLocale, …)` — set for the render, **restored** afterwards (admin ambient locale unchanged). The editing locale is carried from the client (`boot.locale`) to `EditorPreviewController` / `ComponentPreviewController`, validated **fail-closed** against enabled locales; `FrontendEditorRenderer` scopes the resolved owner locale fail-soft. Owner identity stays server-resolved (`EffectiveEditorOwnerResolver`); client owner IDs are never trusted for the effective owner.

For editor surfaces: `resolved effective owner locale == render value locale`. For public: `request locale == effective owner locale == render value locale`.

### Deterministic read policy (INV-PB-L10N-04/05)
`Support\Localize::text($value, $locale)` — the single read boundary:
1. requested locale's value — an explicit `""` is a **real (empty) translation**;
2. else the **approved source (default) locale** value (`LanguageConfigurationContract::defaultLocale()`) — never hardcoded, never the first map entry;
3. else empty (the schema default is applied upstream).
A legacy scalar returns verbatim in every locale. **No array-order guessing. No cross-locale mixing.** `null` value/key = missing (fall back); explicit `""` = present-empty.

### Whole-layout publish unchanged (INV-PB-L10N-06/07)
No per-element/per-field publish lifecycle; no Core, schema, or migration change. Legacy payloads render read-only; no auto-publish, no silent DB rewrite. Pre-fix mixed rows are reconciled operator-side by opening the editor and doing one whole-layout Publish (see PB-STATE-2R).

## Forbidden patterns
Array-order fallback · hardcoded source locale · `app()->setLocale()` without scoped restore · ambient-admin-locale dependency in editor render · per-field/Hero-specific locale logic · duplicate locale resolver · per-element publish.

## Consequences
Editor and public render the same payload identically for a given locale; a component's fields never split locale. Untranslated fields deterministically show the source (fallback) rather than a random map entry. Existing envelopes and scalars keep working with no migration.
