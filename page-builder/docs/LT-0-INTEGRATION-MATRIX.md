# LT-0 — Integration Matrix

**Status: AUDIT (LT-0). No production code, schema, runtime, or UI was changed.**

Classifies every capability Layout Translation needs against the Core surface, then maps the concrete
frontend, source-change, and UX integration points. Companion:
[`LT-0-CORE-ADOPTION-AUDIT.md`](./LT-0-CORE-ADOPTION-AUDIT.md),
[`LT-0-STORAGE-AND-IDENTITY-AUDIT.md`](./LT-0-STORAGE-AND-IDENTITY-AUDIT.md).

---

## 1. Dependency classification

Legend — **S** Stable public · **X** Experimental public · **I** Internal (forbidden) ·
**P** plugin-owned · **U** unavailable (would need Core proposal).

| Capability needed | Core/plugin element | Class | How adopted |
|---|---|---|---|
| Read active public locale | `PublicLocaleContextContract::current()` | S (frozen) | consume (read-only) |
| Read editing locale (admin) | `PublicLocaleContextContract::editingLocale()` / `editing_locale()` | S (frozen) | consume |
| Read enabled/default locales | `LanguageConfigurationContract::enabledLocales()/defaultLocale()` | S (frozen) | consume |
| Translation relationship (locales of a page) | `cms_content_translations(content_id, locale)` via `Content` public helpers | S | consume — never via `*TranslationReadAdapter` (I) |
| Concrete page translation storage | `PageTranslationReadAdapter` / `WriteAdapter` | **I** | **forbidden** — do not reference |
| Immutable content-field history | `RevisionableInterface` / `cms.revision` | S | optional/orthogonal; **not** the sync baseline (prunes) |
| Locale route/URL facts | `LocalizedResourceResolverContract` | S (frozen) | not required for LT (layout is body content, not a route) |
| Per-locale **layout storage** | `pb_layouts` binding | **P** | additive: bind layout to `(owner, locale)` |
| Node cross-locale correspondence | node `data` bag | **P** | additive: correspondence id in `data` |
| Source **baseline** for 3-way sync | `DesignFingerprint` + baseline reference | **P** | additive; content-addressed |
| Structural diff / plan / execution | new plugin services | **P** | additive |
| Locale switcher / status UX | core `HasTranslations` + `TranslationSwitcher` traits | S | reuse pattern (mirror, not couple) |

**Result: zero Internal dependencies, zero `U` gaps.** Everything is `S` (consume) or `P` (additive).
No Core change is required.

---

## 2. Frontend locale-resolution map (current behavior)

```
request → routes/frontend.php (optional /{locale} prefix, whereIn active locales)
  → FrontendController::resolveLocale()      packages/.../FrontendController.php:346-370
        languages->setCurrent(code) → app()->setLocale(code)   (404 if locale inactive)
  → findPublishedContent(slug, type, locale) FrontendController.php:485-493
        (missing translation → abort(404); no implicit fallback)
  → renderContent() → apply_filters('cms.content.body', $translation->content, $content, $ctx)
                                                FrontendController.php:499-551
  → Page Builder filter (priority 20)         plugins/.../PageBuilderServiceProvider.php:455-491
        FrontendRenderer::bodyFor($content->type, $content->id, $classicBody)
  → FrontendRenderer::bodyFor()               plugins/.../Support/FrontendRenderer.php:37-77
        layout = LayoutStore::find(ownerType, ownerId)     ← NO locale argument
        if null | !builder-mode | !published → return $classicBody
        else render published_payload
```

**Key finding:** the layout is selected by `(ownerType, ownerId)` only — **locale-blind**. The same
`published_payload` is served to every locale. Locale currently affects only (a) which
`ContentTranslation` row supplies the classic-body fallback, and (b) `app()->getLocale()` used by
`Localize::text()` for field text inside blocks.

**Current fallback behavior (verified, not to be silently changed):**

| Situation | Current behavior | Cite |
|---|---|---|
| No layout row / classic mode | render classic `$body` | `FrontendRenderer.php:44-49` |
| Layout drafted but not published | render classic `$body` (draft never public) | `FrontendRenderer.php:26,44-49` |
| Locale prefix inactive | `abort(404)` before any lookup | `FrontendController.php:346-370` |
| Valid locale, no content translation | `abort(404)` (no implicit fallback) | `FrontendController.php:26,116-118` |

**Recommended future policy (LT-2/LT-3, to be decided explicitly, not implemented in LT-0):**
`bodyFor` becomes locale-aware — select the target-locale published layout; if absent, fall back to
the **source/default-locale** published layout; if that is absent too, classic body. This must be an
explicit, documented policy, not an accident of lookup.

---

## 3. Source-change notification seams

| Seam | What it signals | Cite | LT use |
|---|---|---|---|
| `LayoutStore::saveDraft/publish` | source layout draft/publish write | `plugins/.../Support/LayoutStore.php:71-102` | the write to observe → mark targets "source changed" (LT-9) |
| `EditorContextController::publish` | source publish request | `plugins/.../Http/Controllers/EditorContextController.php:59-75` | publish boundary |
| Core `LocaleChanged` (Laravel event) | runtime locale switch | `packages/.../Localization/Events/LocaleChanged.php` | not a source-change signal |
| Core translation `do_action` hooks | content translation lifecycle | `TranslationEvents`, `LocalizedEntityEvents` | observability precedent |

**Finding:** there is **no existing "layout published" event**. `LayoutStore` writes are the single
authoritative signal. LT-9 (not LT-0) may add a plugin-owned event/observer that stamps target
layouts as `SOURCE_CHANGED`. Ecommerce precedent: `ProductTranslationLifecycle::synchronize()` sets
`requires_review = true` on all translations after a source save
(`plugins/ecommerce/src/Translation/ProductTranslationLifecycle.php:161-174`).

---

## 4. UX integration points (least-invasive)

Page Builder defines **no Filament Resources**; it extends the core `PageResource`/`PostResource`
edit form via the `cms.form.schema.{page|post}` hook, injecting **`EditorLauncherPanel`** (a `Section`
+ `Placeholder` rendering `editor-mode-switch.blade.php`),
registered in `PageBuilderServiceProvider.php:507-526`. This is the primary, zero-core-change surface.

### Reference UX to mirror

- **Core switcher:** `HasTranslations` + `TranslationSwitcher` traits
  (`packages/.../Filament/Concerns/`). `EditPage`/`EditPost` add `$this->translationSwitcher()` to
  header actions; `?locale=` query param drives editing locale
  (`app/Filament/Admin/Resources/PageResource/Pages/EditPage.php:17-58`).
- **Closest lifecycle reference:** Ecommerce product translation —
  `HasTranslations` + `TranslationSwitcher` + `translationStatusIndicator()` (disabled badge Action) +
  `ProductTranslationLifecycle` (`missing|draft|published` + `requires_review`) +
  `ProductTranslationContext` VO + secondary-locale field locking
  (`plugins/ecommerce/src/Filament/Resources/ProductResource/Pages/EditProduct.php:33-71`;
  `plugins/ecommerce/src/Translation/ProductTranslationLifecycle.php`).

### Concrete attachment points

| Feature | Attachment point | Cite | Change size |
|---|---|---|---|
| Create Translation | `EditorLauncherPanel::viewData()` + builder panel in `editor-mode-switch.blade.php` | `EditorLauncherPanel.php:46-54,73-98` | new button + endpoint; no core change |
| Open target locale | add `?locale=` to `EditorUrls::structure()/live()`; read it in `PageBuilderEditorPage::mount()`; pass to Vue boot payload | `EditorUrls.php:25,31`; `PageBuilderEditorPage.php:80`; `resources/.../editor.blade.php:38` | small, additive params |
| Translation status badge | `EditorLauncherPanel::viewData()` status array + blade status block | `editor-mode-switch.blade.php:152-170` | additive badge |
| Source-changed indicator | same status block; computed from baseline vs source `updated_at`/hash | `pb_layouts.updated_at` | additive badge |
| Review Changes screen | **new** deep-link Filament page (`$shouldRegisterNavigation = false`), modeled on `PageBuilderEditorPage` | `PageBuilderEditorPage.php:80-97` | new page; zero change to existing pages |

**Must remain unchanged outside translation mode:** `PageBuilderEditorPage` /
`PageBuilderFrontendEditorPage` header actions, `DesignLibraryPage`, `PageBuilderDashboardPage`, all
draft/publish API endpoints and payload shapes, and the Classic tab path in
`editor-mode-switch.blade.php`.

### Editor locale gap (confirmed)

`PageBuilderEditorPage::mount(string $ownerType, string $ownerId)` accepts **no locale**; the Vue boot
payload passes `'locale' => app()->getLocale()` — the admin **UI** locale, not the content editing
locale (`editor.blade.php:38`). `EditorUrls::structure()/live()` have **no** locale parameter. LT-3/LT-8
must thread a target locale through these (additive), without altering existing single-language
behavior.
