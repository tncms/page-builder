# LT-M1 · Increment 2D — Browser Acceptance Runbook

Editor-First Locale Controls & Non-Interactive Canvas Governance (Frontend Editor / Live Builder only).

Automated verification is green (see bottom). The browser scenarios below remain **human-gated** — run them in a real browser once, then tick the boxes.

## Preconditions

| Thing | Value |
|---|---|
| Editor | **Live Builder** (Frontend Editor): `/dashboard/page-builder/page/{id}/frontend?locale={code}` |
| Feature flag | `PAGE_BUILDER_TRANSLATION` = **on** (`config('page-builder.translation.enabled')`) for locale scenarios |
| Locales | `en` (default, source), `vi` (translation) both enabled |
| Content #1 | page; EN slug `home` ("Home"), VI slug `trang-chu` ("Trang chủ") |
| One switcher | The locale switcher now lives ONLY in the **left sidebar** above Elements / Navigator / Options — not the top bar |

## Scenario A — Locale loading (left-panel switcher)

1. Open Live Builder EN (`…/page/1/frontend?locale=en`).
2. Left panel top shows **Editing language → English**, sub-line **Source language: English**.
3. EN content loads in the canvas.
4. Open the switcher, pick **Tiếng Việt** (clean → navigates immediately).
5. Left panel shows **Editing language → Tiếng Việt**, sub-line **Translating from: English**.
6. VI layout loads (content_translation owner).
7. Edit + Save VI.
8. Switch back to English.
9. EN is unchanged.

- [ ] Pass

## Scenario B — Unsaved-change guard

With a dirty edit, open the switcher and pick the other locale:

- [ ] **Stay and continue editing** → dialog closes, stays on current locale, edit preserved.
- [ ] **Switch without saving** → no save call, navigates to target locale URL.
- [ ] **Save and switch** → one `saveDraft`, navigates only after success.
- [ ] **Save fails** → stays put (dialog open, error shown), no navigation.
- [ ] No raw `confirm()` dialog appears.

## Scenario C — Canvas non-interaction

Inside the editor canvas, click each of: **Home, Features, Blog, logo, header EN/VI, a CTA button**, and submit any in-page form.

- [ ] No navigation.
- [ ] No locale change.
- [ ] No editor unload.
- [ ] Selecting a block, hovering, context menu, drag/drop, resize, and inspector editing all still work.

## Scenario D — Public frontend interaction (regression)

Outside Page Builder (normal visit to `/home` and `/vi/trang-chu`, no `pb_editor`):

- [ ] Menu links navigate.
- [ ] Language switch works.
- [ ] CTA links navigate.

## Scenario E — Locale render isolation

Publish EN button = `Ready to Start`, VI button = `Bắt đầu`.

- [ ] `/home` → `Ready to Start`.
- [ ] `/vi/trang-chu` → `Bắt đầu`.
- [ ] Change only EN → VI stays `Bắt đầu`.

## Feature-flag off

- [ ] With `PAGE_BUILDER_TRANSLATION` off (or a single enabled locale), the left-panel switcher is hidden; canvas navigation is still blocked (editor UX rule).

---

## Automated verification (this increment)

| Check | Command | Result |
|---|---|---|
| Focused Vitest | `npx vitest run …canvasGuard/BuilderLanguageSwitcher/BuilderSidebar/BuilderTopBar` | **26/26 pass** |
| Full Vitest | `npx vitest run` | **1403/1403 pass** (168 files, +11 new) |
| Production build | `npm run build:frontend` | **shell.js + iframe.js built** |
| PHP locale isolation | `php artisan test …/PageBuilderLocaleAwareEditorTest.php` | **8/8 pass** (owner resolution EN→page / VI→content_translation, draft/publish isolation, locale carried, fail-closed) |

Covers: switcher placement (above tabs, exactly one, hidden ≤1 locale / flag-off), source vs. "Translating from" presentation, dirty-guard flow (Stay / Switch-without-saving / Save-and-switch / save-failure), canvas anchor + auxclick + form-submit interception with propagation preserved, and Structure-Editor owner/draft/publish locale isolation.
