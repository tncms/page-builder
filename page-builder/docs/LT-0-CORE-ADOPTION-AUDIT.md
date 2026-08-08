# LT-0 — Core Adoption & Existing Architecture Audit

**Status: AUDIT (LT-0). No production code, schema, runtime, or UI was changed.**

This document is the source-verified inventory of the **frozen Core Translation Platform v1.0** and
the Core seams the Page Builder plugin will adopt for Layout Translation. Every claim is cited to
`path:line` in `packages/thenguyen/cms-core` (Core) or `plugins/page-builder` (plugin). It was
produced by reading source, not documentation alone; discrepancies are noted.

Companion documents: [`LT-0-INTEGRATION-MATRIX.md`](./LT-0-INTEGRATION-MATRIX.md),
[`LT-0-STORAGE-AND-IDENTITY-AUDIT.md`](./LT-0-STORAGE-AND-IDENTITY-AUDIT.md),
[`LT-0-SYNCHRONIZATION-ADR.md`](./LT-0-SYNCHRONIZATION-ADR.md).

---

## 1. Executive summary

- Core exposes a **stable, frozen** translation/localization/revision surface. Page Builder can adopt
  it **read-only**: current locale, enabled locales, and the existing per-locale translation
  relationship. It needs to **register nothing** as a translation driver.
- The Core "translation relationship" is **not** a group table. It is a **parent-child foreign key**:
  one `cms_contents` row is the group anchor; `cms_content_translations(content_id, locale)` overlays
  per-locale text. A page's translation set = its `ContentTranslation` rows.
- **Locale authority is Core-only and read-only for plugins.** `PublicLocaleContextContract` has no
  setter by design (`packages/.../Localization/Contracts/PublicLocaleContextContract.php:14-17`).
- The Core **Revision Platform** (`cms.revision`) is `@stable`, but it snapshots a *field map* per
  `(entity, locale)` and **prunes** to `max_per_locale` (default 5). It is therefore **not** a safe
  store for a durable sync baseline. Baseline is plugin-owned (see the ADR).
- **No Core change is required** for Layout Translation.

---

## 2. Core public-port inventory

### 2.1 Translation contracts (`packages/thenguyen/cms-core/src/Translation/Contracts`)

| Contract | Key surface | Stability |
|---|---|---|
| `TranslationDriverInterface` | `get/has/all(TranslationKey,locale,ctx)` | **@stable @since 1.0** (`TranslationDriverInterface.php:19`) |
| `RelationalTranslationDriverInterface` | full CRUD relational driver; `diagnostics()` must never throw | **@stable @since 1.0** (`RelationalTranslationDriverInterface.php:26`) |
| `TranslationResolverInterface` | `resolve/resolveValue → TranslationResult`; owns locale + fallback + cache | **@stable @since 1.0** (`TranslationResolverInterface.php:19`) |
| `LocaleRegistryInterface` | engine locale registry: `enabled()`, `default()`, `fallback()` | **@stable @since 1.0** (`LocaleRegistryInterface.php:18`) |
| `TranslationCacheInterface` | `get/put/has/forget/flush` | **@stable @since 1.0** (`TranslationCacheInterface.php:16`) |
| `TranslationRepositoryInterface` | `find/save/forget` persistence seam | Experimental (Null-bound by default) |
| `LocalizedContentContract` | `localizedContentType()`, `localizedFieldDefinitions()` | Experimental public extension seam (Phase 8.4) |

### 2.2 Localization contracts (`packages/thenguyen/cms-core/src/Localization/Contracts`)

| Contract | Key surface | Stability |
|---|---|---|
| `PublicLocaleContextContract` | `current()`, `default()`, `isPublic()`, `adminLocale()`, `editingLocale()` — **read-only, no setter** | Frozen by governance (`PLATFORM_LOCALIZATION_ROUTE_FREEZE.md`) |
| `LanguageConfigurationContract` | `multilingualEnabled()`, `enabledLocales()`, `defaultLocale()`, `isEnabled()`, `prefixFor()`, `routingStrategy()` | Frozen by governance |
| `LocalizationStrategyContract` | `key()`, `resolveLocale()`, `url()`, `switchMethod()` | Frozen by governance |
| `LocalizedResourceResolverContract` | `key()`, `priority()`, `supports()`, `descriptorFor(ctx,locale): ?RouteDescriptor` | Frozen by governance |

### 2.3 Revision contracts (`packages/thenguyen/cms-core/src/Revision/Contracts`)

| Contract | Key surface | Stability |
|---|---|---|
| `RevisionableInterface` | `revisionEntityType/Id()`, `revisionableLocales()`, `snapshotForLocale(locale): array` | **@stable @since 1.0** (`RevisionableInterface.php:15`) |
| `RevisionManagerInterface` (`cms.revision`) | `createRevision/latest/history/exists/prune/diagnostics` | **@stable @since 1.0** (`RevisionManagerInterface.php:19`) |

### 2.4 Registries and their registration API

| Registry | Bind name | Register via | Failure mode |
|---|---|---|---|
| `TranslationDriverRegistry` | `cms.translation.drivers` | `register(TranslationDriverInterface, asDefault)` | duplicate name silently overwrites |
| `LocalizedContentRegistry` | `cms.translation.content.registry` | `register(LocalizedContentContract)` / `registerType(type, defs)` | — |
| `LocalizationStrategyRegistry` | (Core) | `register(LocalizationStrategyContract)` | fail-fast on duplicate key |
| `LocalizedResourceResolverRegistry` | `cms.localization.resolvers` | `register(LocalizedResourceResolverContract)` | fail-fast on duplicate key |

> **Page Builder registers with none of these for Layout Translation.** It has no per-field
> translation *driver* to contribute — layout text localization already works today via
> `LocalizedValue` maps and `Localize::text()` (§4). It only *consumes* locale read-ports.

### 2.5 Locked public container bindings

`cms.translation`, `cms.translation.registry`, `cms.translation.resolver`, `cms.translation.cache`,
`cms.translation.drivers`, `cms.translation.driver.content`, `cms.translation.driver.term`,
`cms.translation.rollout_diagnostics`, `cms.revision` — locked by
`docs/translation/ARCHITECTURE_LOCK.md`. All 30+ bindings are declared in
`packages/.../Translation/TranslationServiceProvider.php`.

---

## 3. Locale authority (what the plugin reads)

- Active public locale is owned by `LanguageManager` (`cms.language`) and set by the routing-strategy
  middleware; `FrontendController::resolveLocale()` calls `languages->setCurrent()` which calls
  `app()->setLocale()` (`packages/.../Http/Controllers/FrontendController.php:346-370`;
  `LanguageManager.php:158-163`).
- Read the current public-content locale via **`PublicLocaleContextContract::current()`**
  (`PublicLocaleContext.php:29` → `languages->currentCode()`). **No setter exists** — plugins consume,
  never mutate (`PublicLocaleContextContract.php:14-17`).
- Read the enabled/public locale set via **`LanguageConfigurationContract::enabledLocales()`**
  (`LanguageConfiguration.php:34` → `LanguageManager::getPublicLocales()`) and `defaultLocale()`
  (`:39`), `isEnabled()` (`:49`).
- The admin **editing** locale (content translation being edited) is `editing_locale()` /
  `PublicLocaleContextContract::editingLocale()`, distinct from the admin **UI** locale (`?lang=`).

---

## 4. Translation relationship model (the group anchor)

**There is no "translation group id".** The source `cms_contents.id` is the anchor and all locales
share it via a child overlay:

`packages/.../database/migrations/2026_05_28_000003_create_cms_content_translations_table.php`
```php
$table->unsignedBigInteger('content_id')->index();       // FK → cms_contents.id, cascadeOnDelete
$table->string('locale', 20)->default('vi')->index();
// ... title, slug, excerpt, content, meta_* ...
$table->unique(['content_id', 'locale'], 'cms_content_translations_content_locale_unique');
```

- One `Content` row spans **all** locales (`Content.php` has no `locale` column; `translations()` is a
  `hasMany` on `content_id`, `Content.php:92-95`).
- A page's translation set for a locale = "does a `ContentTranslation(content_id, locale)` row exist".
- Field-level text localization within Page Builder already works two ways:
  - Homepage/preset layouts: `SectionFieldLocalizer::resolveForLocale()` collapses
    `LocalizedValue` maps `{"vi":…,"en":…}` (`packages/.../Services/SectionFieldLocalizer.php:43-46`).
  - Page/post builder layouts: block views read `app()->getLocale()` via
    `Localize::text()` (`plugins/page-builder/src/Support/Localize.php:19-43`).

**Implication:** text can already vary by locale inside a *shared* structure. Layout Translation adds
the missing dimension — a **per-locale structure** — which the current one-row-per-owner model cannot
express (see [`LT-0-STORAGE-AND-IDENTITY-AUDIT.md`](./LT-0-STORAGE-AND-IDENTITY-AUDIT.md) §3).

---

## 5. Revision Platform as a baseline candidate — evaluated and rejected

`RevisionableInterface` + `RevisionManagerInterface` (`cms.revision`) are `@stable` and provide
immutable, locale-scoped snapshots. However:

- `snapshotForLocale()` returns a **field map** (`RevisionableInterface.php`), designed for content
  translation fields — not an arbitrary layout JSON document.
- History is **pruned** to `max_per_locale` (default 5, `keep_latest`) per `config/revisions.php:19-48`.
  A sync baseline that a target may diverge from for weeks would be **silently pruned away**,
  breaking the three-way comparison.
- Capture is **best-effort and fault-isolated** — deliberately non-authoritative.

**Decision:** Core revisions are excellent for content-field history but **must not** be the durable
sync baseline. The baseline is plugin-owned and content-addressed (see the ADR). Page Builder *may*
still opt its layout into `cms.revision` later for editor history, orthogonally.

---

## 6. Feature flags & diagnostics conventions to mirror

- Translation flags live in `config/translation.php` (`enabled`, `modules.{posts,pages,terms}
  .{read,write}_driver` with `legacy|adapter`, default `adapter`). Rollback = `legacy`, no migration.
- Revision flags in `config/revisions.php` (`enabled`, `entities.pages`, `max_per_locale`).
- Diagnostics are **programmatic and never throw**: `cms.translation.rollout_diagnostics->report()`
  (`TranslationRolloutDiagnostics.php`) and per-driver/adapter `diagnostics()` arrays.
- **Layout Translation must follow the same conventions:** a plugin config flag (default **off**
  until LT-10), and a never-throwing diagnostics report. Events are observability-only.

---

## 7. Stability classification summary

| Tier | Members (relevant to this work) | Rule |
|---|---|---|
| **Stable / Frozen** | the 7 `@stable` translation+revision interfaces; the 4 governance-frozen Localization contracts; `AbstractRelationalTranslationDriver`; VO factories; locked `cms.*` bindings | Frozen for v1.x; additive-only; a break needs v2.0 + ADR |
| **Experimental public** | `TranslationRepositoryInterface`, `LocalizedContentContract`, contracts not yet `@stable` | may change within v1.x |
| **Internal (do not depend)** | `PostTranslationReadAdapter`, `PageTranslationReadAdapter`, write adapters, provider internals | forbidden dependency for the plugin |

**Discrepancy noted:** the four Localization contracts (`PublicLocaleContextContract`,
`LanguageConfigurationContract`, `LocalizationStrategyContract`, `LocalizedResourceResolverContract`)
are **frozen by governance docs** but carry **no `@stable` tag in source**. The plugin treats them as
Stable-by-freeze but should pin usage to the documented read methods only.

---

## 8. What Page Builder will adopt (contract list)

1. `PublicLocaleContextContract::current()` / `editingLocale()` — read active/editing locale.
2. `LanguageConfigurationContract::enabledLocales()` / `defaultLocale()` — the locale set.
3. Core `(content_id, locale)` translation relationship — read a page's translation set via the
   `Content` model's public translation helpers (never via internal adapters).
4. (Optional, later) `cms.revision` for editor-side layout history — orthogonal to sync baseline.

Nothing is registered into Core; nothing frozen is modified.
