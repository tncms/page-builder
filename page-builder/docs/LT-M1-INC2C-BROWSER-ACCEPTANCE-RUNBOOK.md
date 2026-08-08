# LT-M1 — Increment 2C: Manual Browser Acceptance Runbook

**Purpose:** final acceptance gate for the locale-aware Page Builder editor (LT-M1).
This is a **manual** runbook — no automation is installed. A human tester executes
each step in a real browser and captures the evidence. **LT-M1 may be declared
COMPLETE only if every gate (A–F) passes.**

> This document is verification-only. It changes no code. It reflects the 2A + 2B
> implementation as built. If a step fails, use the **Failure Diagnosis Table** at
> the end to locate the smallest plugin-side fault area, then re-run only the failed
> scenario.

---

## 0. Environment facts (verified from this install)

| Thing | Value |
|---|---|
| Admin base path | `/dashboard` (`config('cms.admin_path')`) |
| Live Builder (editor) route | `/dashboard/page-builder/page/{id}/frontend?locale={code}` |
| Structure Builder route | `/dashboard/page-builder/page/{id}/edit?locale={code}` |
| Page edit form (launcher lives here) | `/dashboard/pages/{id}/edit` |
| API prefix | `/api/page-builder` |
| Context / Draft / Publish endpoints | `…/editor/context/page/{id}[?locale=]`, `…/draft[?locale=]`, `…/publish[?locale=]` |
| Command / Preview endpoints | `…/state/command`, `…/editor/preview` (no `?locale=` — see notes) |
| Default locale | `en` (**not** URL-prefixed) → EN home = `/home` |
| Second locale | `vi` (prefixed) → VI page = `/vi/trang-chu` |
| Content #1 | type=`page`; EN slug=`home` (title "Home"); VI slug=`trang-chu` (title "Trang chủ") |
| Feature flag | `PAGE_BUILDER_TRANSLATION` — **OFF by default** |

> **Acceptance-text note:** the acceptance spec writes `/en/home`. This install does
> **not** prefix the default locale, so the real EN URL is **`/home`** (or `/`). Use
> `/home` for EN and `/vi/trang-chu` for VI. If your install has
> `prefixesDefaultLocale()=true`, use `/en/home` instead.

### 0.1 Preconditions (do these first)

1. **Enable the feature flag.** In `.env`: `PAGE_BUILDER_TRANSLATION=true`, then
   `php artisan config:clear`. Confirm:
   `php artisan tinker --execute='echo config("page-builder.translation.enabled")?"ON":"OFF";'` → **ON**.
   *(Scenarios A–E require ON. Scenario F requires OFF.)*
2. **Assets are built.** `public/vendor/page-builder/frontend-editor/shell.js` exists
   (built in 2B). If missing: `cd plugins/page-builder && npm run build`.
3. **Log in** to `/dashboard` as a user holding `page-builder.edit` **and**
   `page-builder.publish`.
4. **Capture the pre-state DB baseline** (Scenario E "before"):
   ```
   php artisan tinker --execute='
   foreach (DB::table("pb_layouts")->orderBy("id")->get() as $r) echo "layout {$r->id} {$r->owner_type}/{$r->owner_id} {$r->editor_mode} ".($r->published_payload?"pub":"draft")."\n";
   echo "bindings=".DB::table("pb_layout_translation_bindings")->count()." baselines=".DB::table("pb_layout_translation_baselines")->count()."\n";'
   ```
   Expected at start of this install: `layout 1 page/1 builder pub`, `layout 2
   content_translation/1 builder draft`, `bindings=1 baselines=1`.

### 0.2 Standing evidence to capture on EVERY editor load

- **Screenshot:** full editor window (top bar + canvas).
- **Console (F12 → Console):** must be free of red errors; specifically no
  `TN_PB_FRONTEND_EDITOR_BOOT` undefined, no Vue mount error, no 4xx/5xx thrown.
- **Boot payload check (Console):** run
  `TN_PB_FRONTEND_EDITOR_BOOT.locale` and
  `TN_PB_FRONTEND_EDITOR_BOOT.context.translation.available_locales` — screenshot the output.
- **Network (F12 → Network, filter `page-builder`):** keep it open across the whole run.

---

## Scenario A — Editor Switching

Goal: EN and VI edit **distinct** layouts; switching is a **full navigation** to a
server-generated URL; content never bleeds across locales.

### A1. Open the EN editor
1. **Action:** navigate to `/dashboard/page-builder/page/1/frontend?locale=en`.
   (Or open `/dashboard/pages/1/edit`, use the "UX Page Builder" launcher → Live
   Builder; confirm the resulting URL carries `?locale=en`.)
2. **Expected:** editor loads; **language switcher** (🌐 top-right of the top bar)
   shows current = **English**; opening it lists **English (source)** and **Tiếng Việt**,
   with English marked current/disabled.
3. **Evidence:** screenshot of the open switcher menu; Console:
   `TN_PB_FRONTEND_EDITOR_BOOT.context.translation.resolved_locale` → `"en"`.
4. **Failure meaning:** switcher missing → projection empty or flag off; wrong current →
   boot locale wrong.
5. **Smallest fault area:** `resources/frontend-editor/shell/components/BuilderLanguageSwitcher.vue`
   (visibility `availableLocales.length > 1`), `src/Editor/Translation/LocaleSwitchProjector.php`
   (flag gate / projection), boot `resources/views/filament/pages/frontend-editor.blade.php`.

### A2. Confirm EN content
1. **Action:** read the canvas heading.
2. **Expected:** the EN layout's content (this install's EN draft). For the acceptance
   flow, treat the starting heading as **"Ready to Start"** — if it differs, that is the
   real current EN content; proceed, you will overwrite it.
3. **Evidence:** screenshot of the EN canvas.
4. **Failure meaning:** empty canvas → EN layout not loaded (owner mis-resolved).
5. **Smallest fault area:** `src/Support/EditorContext.php` (`forCanonicalLocale`),
   `src/Editor/Translation/EffectiveEditorOwnerResolver.php`.

### A3. Edit EN + Save Draft
1. **Action:** change the heading to **"Ready to Start EN"**. Click **Save**.
2. **Expected:** Save button enables only when dirty, shows "Saving…", then settles.
3. **Evidence (Network):** one **`POST /api/page-builder/editor/context/page/1/draft?locale=en`**
   → `200 {success:true}`. Screenshot the request URL (note **`?locale=en`**) + response.
4. **Failure meaning:** no `?locale=en` on the draft POST → boot API URL not locale-baked;
   404/422 → owner resolution / permission.
5. **Smallest fault area:** blade boot `api.draft` (`$localeQuery`) in
   `frontend-editor.blade.php`; `src/Http/Controllers/EditorContextController.php@saveDraft`.

### A4. Switch to VI (clean)
1. **Action:** open the 🌐 switcher → click **Tiếng Việt**.
2. **Expected:** **full page navigation** to
   `/dashboard/page-builder/page/1/frontend?locale=vi`; VI editor opens; switcher current = **Tiếng Việt**.
3. **Evidence:** screenshot of the address bar (`?locale=vi`) + VI editor; Console
   `TN_PB_FRONTEND_EDITOR_BOOT.context.translation.layout_owner_type` → `"content_translation"`.
4. **Failure meaning:** in-place swap with no navigation → wrong switch mechanism; opens
   EN content → owner not re-resolved (shared layout bug).
5. **Smallest fault area:** `BuilderLanguageSwitcher.vue` (`navigate`/`editor_url`),
   `LocaleSwitchProjector` (`editor_url` build via `EditorUrls::live`).

### A5. Edit VI + Save Draft
1. **Action:** change the VI heading to **"Bắt đầu"**. Click **Save**.
2. **Expected:** **`POST …/draft?locale=vi`** → `200`.
3. **Evidence:** Network screenshot showing **`?locale=vi`**.
4. **Failure meaning:** POST carries `locale=en` → stale boot after navigation.
5. **Smallest fault area:** blade boot `$localeQuery` / `$resolvedLocale`.

### A6. Switch back to EN — isolation proof
1. **Action:** 🌐 switcher → **English**. (Editor is clean after A5, so it navigates
   immediately.)
2. **Expected:** EN editor shows **"Ready to Start EN"** — **no Vietnamese content**.
3. **Evidence:** screenshot of EN canvas; screenshot address bar `?locale=en`.
4. **Failure meaning:** VI text appears in EN → **isolation failure** (both locales share a row).
5. **Smallest fault area:** `EffectiveEditorOwnerResolver` (source vs translation owner),
   `src/Support/LayoutStore.php` keying.

**Gate A passes when:** EN and VI show independent content, each switch is a full
navigation to the correct `?locale=` URL, and A6 shows no cross-locale bleed.

---

## Scenario B — Unsaved-Change Guard

### B1. Make an unsaved EN edit
1. **Action:** in EN editor, change the heading (e.g. "Ready to Start EN 2"). **Do NOT Save.**
2. **Expected:** Save button becomes enabled (editor is dirty).
3. **Evidence:** screenshot showing enabled Save.
4/5. n/a.

### B2. Attempt to switch → dialog appears
1. **Action:** open 🌐 → click **Tiếng Việt**.
2. **Expected:** the **unsaved-change dialog** appears (modal). Title "Unsaved changes";
   body names the target ("Tiếng Việt"); three buttons: **Stay and continue editing**,
   **Switch without saving**, **Save and switch**. Focus lands on **Stay**. **No navigation yet.**
3. **Evidence:** screenshot of the dialog; Network shows **no** navigation / no draft POST yet.
4. **Failure meaning:** navigates immediately with no dialog → dirty state not detected;
   raw `confirm()` box → wrong dialog.
5. **Smallest fault area:** `BuilderLanguageSwitcher.vue` (`isDirty` = `store.dirty || store.inspectorDirty`,
   `decideLocaleSwitch`), `BuilderLocaleSwitchDialog.vue`.

### B3. "Stay"
1. **Action:** click **Stay and continue editing** (or press **Esc**).
2. **Expected:** dialog closes; editor **remains on EN**; the unsaved edit is intact; **no navigation**.
3. **Evidence:** screenshot (still EN, Save still enabled); Network shows no request.
4. **Failure meaning:** navigates / discards → Stay handler wrong.
5. **Smallest fault area:** `BuilderLanguageSwitcher.vue@onStay`, dialog `@stay`/Escape.

### B4. "Switch without saving"
1. **Action:** trigger the switch again (🌐 → Tiếng Việt) → click **Switch without saving**.
2. **Expected:** **full navigation** to `?locale=vi`; the unsaved EN change is **discarded**
   (re-open EN later → the B1 edit is gone; A5's saved "Bắt đầu"/"Ready to Start EN" persists);
   **no draft POST** was sent for the discarded change.
3. **Evidence:** Network shows **navigation only, no `/draft` POST**; screenshot VI editor.
4. **Failure meaning:** a draft POST fired → it saved instead of discarding.
5. **Smallest fault area:** `BuilderLanguageSwitcher.vue@onDiscard` (`switchWithoutSaving`).

### B5. "Save and switch"
1. **Action:** in EN, make a new unsaved edit → 🌐 → Tiếng Việt → click **Save and switch**.
2. **Expected order:** button shows "Saving…"; **`POST …/draft?locale=en`** → `200`; **then**
   full navigation to `?locale=vi`. The saved EN text is present when you return to EN.
3. **Evidence:** Network screenshot showing the **draft POST precedes** the navigation;
   screenshot of the persisted EN text afterward.
4. **Failure meaning:** navigates before/without saving → ordering bug; navigates after a
   failed save → failure not respected.
5. **Smallest fault area:** `resources/frontend-editor/shell/localeSwitch.ts@saveAndSwitch`
   (awaits `save()`, navigates only if `!stillDirty()`), `BuilderLanguageSwitcher.vue@onSave`.

### B6. Save-and-switch FAILURE path (optional but recommended)
1. **Action:** simulate a save failure — temporarily block the draft endpoint (DevTools →
   Network → right-click the draft request → *Block request URL*), make an edit, choose
   **Save and switch**.
2. **Expected:** save fails; **no navigation**; dialog stays open; the existing typed save
   error is shown; editor stays dirty. Unblock the URL afterward.
3. **Evidence:** screenshot of the dialog still open + error; Network shows the failed draft, no navigation.
4. **Failure meaning:** navigates despite failed save → `stillDirty()` guard bypassed.
5. **Smallest fault area:** `localeSwitch.ts@saveAndSwitch` (`stillDirty()` check).

**Gate B passes when:** dirty switch always shows the dialog; Stay/Discard/Save behave
exactly as above; a failed save never navigates.

---

## Scenario C — Preview (in-editor canvas)

The Live Builder canvas is an **iframe of the real page** carrying the editing locale;
it renders that locale's **draft**.

### C1. EN preview
1. **Action:** with the EN editor open, inspect the canvas iframe `src` (F12 → Elements,
   or Network → Doc).
2. **Expected:** iframe `src` = `http://<host>/home?pb_editor=1&pb_owner_type=page&pb_owner_id=1&locale=en`;
   canvas shows **English** draft ("Ready to Start EN").
3. **Evidence:** screenshot of the iframe `src` + rendered EN canvas.
4. **Failure meaning:** canvas shows VI / classic body → preview owner mis-resolved.
5. **Smallest fault area:** `src/Support/FrontendEditorRenderer.php` (`resolveForReadOrCanonical`
   from `?locale=`), blade `$iframeUrl` locale param.

### C2. VI preview
1. **Action:** switch to VI editor; inspect the canvas iframe `src`.
2. **Expected:** `src` … `&locale=vi`; canvas shows **Vietnamese** draft ("Bắt đầu").
   **No owner mixing** (EN text must not appear).
3. **Evidence:** screenshot of iframe `src` (`locale=vi`) + VI canvas.
4. **Failure meaning:** EN content in VI preview → source layout leaked into preview.
5. **Smallest fault area:** `FrontendEditorRenderer::renderInner` effective-owner resolution.

**Gate C passes when:** each locale's preview renders that locale's draft, `src` carries
the matching `locale=`, and there is no cross-owner mixing.

---

## Scenario D — Frontend Rendering

Publish first, then verify the public pages.

### D1. Publish both locales
1. **Action:** in EN editor click **Publish**; in VI editor click **Publish**.
2. **Expected:** **`POST …/publish?locale=en`** and **`POST …/publish?locale=vi`**, each `200`.
3. **Evidence:** Network screenshots of both publish calls (note the differing `?locale=`).
4. **Failure meaning:** both carry the same locale → boot URL not locale-scoped.
5. **Smallest fault area:** blade boot `api.publish` `$localeQuery`;
   `EditorContextController@publish`.

### D2. EN frontend
1. **Action:** open **`/home`** (default locale, unprefixed) in a normal tab (not the editor).
2. **Expected:** renders the EN published builder layout → **"Ready to Start EN"**.
3. **Evidence:** screenshot; **refresh 3–5×** → identical (no cache bleed).
4. **Failure meaning:** shows VI / classic → public locale resolution wrong.
5. **Smallest fault area:** `src/Support/FrontendRenderer.php` (`currentLocale()` via
   `PublicLocaleContextContract`, `resolveForReadOrCanonical`).

### D3. VI frontend
1. **Action:** open **`/vi/trang-chu`**.
2. **Expected:** renders the VI published builder layout → **"Bắt đầu"**.
3. **Evidence:** screenshot; refresh 3–5× → identical; and re-open `/home` → still
   "Ready to Start EN" (**no cross-locale cache bleed**).
4. **Failure meaning:** VI shows EN text → effective owner not applied on the public path;
   VI shows classic body → VI layout not published (redo D1 for VI).
5. **Smallest fault area:** `FrontendRenderer::bodyFor` effective-owner resolution / publish state.

**Gate D passes when:** `/home`→EN text, `/vi/trang-chu`→VI text, stable across refreshes,
neither bleeds into the other.

---

## Scenario E — DB Isolation

Run **after** A–D. Read-only.

1. **Action:**
   ```
   php artisan tinker --execute='
   foreach (DB::table("pb_layouts")->orderBy("id")->get() as $r) echo "layout {$r->id} {$r->owner_type}/{$r->owner_id} {$r->editor_mode} ".($r->published_payload?"pub":"draft")."\n";
   foreach (DB::table("pb_layout_translation_bindings")->get() as $b) echo "binding {$b->id} {$b->page_translation_key} layout_id={$b->layout_id}\n";
   echo "bindings=".DB::table("pb_layout_translation_bindings")->count()." baselines=".DB::table("pb_layout_translation_baselines")->count()."\n";'
   ```
2. **Expected:**
   - EN owner = **`page/1`** → one layout id (e.g. **1**).
   - VI owner = **`content_translation/1`** → a **distinct** layout id (e.g. **2**).
   - **Exactly one** binding (`page:1:vi` → the VI layout id).
   - **Exactly one** baseline (`source_layout_id` = the EN layout id). **No duplicate baseline.**
3. **Evidence:** paste the command output; record final **layout ids**, **binding id**, **baseline id**.
4. **Failure meaning:** same layout id for both owners → isolation failure; >1 binding or
   >1 baseline → non-idempotent provisioning.
5. **Smallest fault area:** `src/Editor/Translation/FirstOpenLayoutProvisioner.php`
   (idempotency), `EffectiveEditorOwnerResolver`.

**Reference (this install, pre-run):** `layout 1 page/1`, `layout 2 content_translation/1`,
`bindings=1`, `baselines=1` (source_layout_id=1). These must **not multiply** after A–D.

---

## Scenario F — Flag-Off Regression

1. **Action:** set `.env` `PAGE_BUILDER_TRANSLATION=false`; `php artisan config:clear`.
   Reload the EN editor `/dashboard/page-builder/page/1/frontend`.
2. **Expected (legacy):**
   - **No language switcher** in the top bar (`available_locales` empty).
   - Editor edits the single shared (source) layout as before.
   - Frontend `/home` and `/vi/trang-chu` both resolve the **canonical** layout (legacy).
   - No new `pb_layouts` / binding / baseline rows are created by opening the editor.
3. **Evidence:** screenshot (no switcher); DB counts unchanged from Scenario E; screenshot
   of frontend rendering.
4. **Failure meaning:** switcher still shows with flag off → §11 gate broken.
5. **Smallest fault area:** `src/Editor/Translation/LocaleSwitchProjector.php`
   (`config('page-builder.translation.enabled')` early-return `[]`).
6. **Restore:** set the flag back to your desired state and `php artisan config:clear`.

**Gate F passes when:** flag-off hides the switcher and preserves legacy single-layout
behavior with no new rows.

---

## Network `?locale=` propagation — quick reference

| Call | Method / URL | Locale carried? |
|---|---|---|
| Editor page load | `GET /dashboard/page-builder/page/1/frontend?locale={code}` | **yes** (query) |
| Preview iframe | `GET /home?pb_editor=1&pb_owner_type=page&pb_owner_id=1&locale={code}` | **yes** |
| Save draft | `POST /api/page-builder/editor/context/page/1/draft?locale={code}` | **yes** (baked in boot) |
| Publish | `POST /api/page-builder/editor/context/page/1/publish?locale={code}` | **yes** |
| Context (if re-fetched) | `GET /api/page-builder/editor/context/page/1?locale={code}` | **yes** |
| State command | `POST /api/page-builder/state/command` | **no** (structural op, owner-agnostic — expected) |
| Editor preview render | `POST /api/page-builder/editor/preview` | **no** (renders posted payload — expected) |

> The shell boots from the **inline** `TN_PB_FRONTEND_EDITOR_BOOT` payload, so there may
> be **no** context XHR on load. Observe `?locale=` on the **draft/publish** calls; that is
> the authoritative propagation signal.

---

## Browser console checks (every editor load)

- No red errors.
- `TN_PB_FRONTEND_EDITOR_BOOT.locale` == the URL's `locale`.
- `TN_PB_FRONTEND_EDITOR_BOOT.context.translation.available_locales` is a 2-item array
  (EN source + VI) when flag ON; `[]`/absent when flag OFF.
- `TN_PB_FRONTEND_EDITOR_BOOT.api.draft` ends with `?locale={current}`.

---

## Failure Diagnosis Table

| Symptom | Likely root cause | Smallest plugin-side fault area |
|---|---|---|
| Switcher not shown (flag ON) | projection empty / boot lacks translation block | `LocaleSwitchProjector.php`; `EditorContext.php@forResolvedOwner`; `frontend-editor.blade.php` boot |
| Switcher shown (flag OFF) | §11 gate not applied | `LocaleSwitchProjector.php` (config early-return) |
| Switch does nothing / same locale | `editor_url` null or equals current | `LocaleSwitchProjector` (`EditorUrls::live`); `BuilderLanguageSwitcher.vue@select` |
| Switch is in-place, no navigation | wrong switch mechanism | `BuilderLanguageSwitcher.vue@navigate`, `localeSwitch.ts` |
| VI editor opens EN content | effective owner not re-resolved | `EffectiveEditorOwnerResolver.php`; `EditorContext@forCanonicalLocale` |
| Draft/publish POST missing `?locale=` | boot API URLs not locale-baked | `frontend-editor.blade.php` (`$localeQuery`) |
| No unsaved dialog on dirty switch | dirty not detected / decision wrong | `BuilderLanguageSwitcher.vue` (`isDirty`), `localeSwitch.ts@decideLocaleSwitch` |
| Dialog navigates before saving | ordering bug | `localeSwitch.ts@saveAndSwitch` |
| Navigates after a failed save | failure guard bypassed | `localeSwitch.ts@saveAndSwitch` (`stillDirty()`) |
| Preview shows wrong locale | preview owner from wrong locale | `FrontendEditorRenderer.php@renderInner`; blade `$iframeUrl` |
| `/home` or `/vi/...` shows wrong locale | public locale resolution | `FrontendRenderer.php@bodyFor` / `currentLocale()` |
| Non-default locale shows classic body | translated layout not published | (data) republish that locale; else `FrontendRenderer` fallback |
| Two owners share one layout id | isolation failure | `EffectiveEditorOwnerResolver.php`; `LayoutStore` keying |
| >1 binding or >1 baseline | non-idempotent provisioning | `FirstOpenLayoutProvisioner.php` |
| Editor 4xx on load with a valid locale | fail-closed guard mis-firing | `PageBuilderFrontendEditorPage.php@guardAndProvisionLocale` |

---

## Sign-off

- [ ] A — Editor switching
- [ ] B — Unsaved-change guard
- [ ] C — Preview
- [ ] D — Frontend rendering
- [ ] E — DB isolation
- [ ] F — Flag-off regression

**LT-M1 is COMPLETE only when all six boxes are checked with attached evidence**
(screenshots + console + network + DB output). If any gate fails: record the exact
failing step, use the diagnosis table, apply the smallest plugin-only fix, and re-run
**only** the failed scenario.
