# Page Template Application Workflow

_Phase 26.47D — the implementation of the Page Template application workflow
frozen in [page-template-application.md](page-template-application.md) (Part A of
the 26.47C product spec)._

This phase implements Part A only. Portable asset packaging (Part B of the
original 26.47D draft) is **deferred** — see
[§ Deferred: portable media](#deferred-portable-media). The `.tncms-design`
package format (Phase 26.47) and the CMS Media Manager are **unchanged**.

## What this phase adds

- Frozen terminology: full-page actions read **Use Template** / **Use Templates**
  / **Use This Template**. "Import" is reserved for package ingestion
  (**Import Package**).
- A Design Library **destination workflow** for admins: Create New Page/Post or
  Use Existing Page/Post, then apply and open the Builder.
- One orchestration service (`TemplateApplicationService`) that always applies
  through the unchanged `FullPageImportService` — draft only, snapshotted,
  transactional, never publishing.

Everything else — the runtime, repository, Runtime Preview, Builder, serialization,
BuilderState, the CommandBus, the Extension API, and Package Sharing — is untouched.

---

## 1. Terminology (frozen)

| Surface | Label | File |
| --- | --- | --- |
| Builder toolbar (page/post) | **Use Templates** | `resources/views/builder/resource-picker/toolbar.blade.php` |
| Design Library page card | **Use This Template** | `resources/views/design-library/card.blade.php` |
| Picker/apply dialog | **Use Template** | `PickerContext::actionLabel()`, `resources/views/design-library/index.blade.php` |
| Package ingestion | **Import Package** | `resources/views/design-library/header.blade.php`, `import-dialog.blade.php` |

"Import" MUST NOT appear on a Page Template control. It survives only on the
`.tncms-design` package controls.

---

## 2. Builder workflow (editing a Page/Post)

The editor toolbar's **Use Templates** button opens the Design Library in
**Picker mode** scoped to `kind=page` with the current owner document (the 26.47B
bridge). Selecting a template opens the apply dialog, which:

- shows the current-vs-incoming **section/block counts** (from the read-only
  `FullPageImportService::plan()`);
- states the published layout stays unchanged until Publish;
- **always confirms before replacing** a non-empty draft;
- applies through `FullPageImportService::import()` — draft only, with the
  recovery snapshot, optimistic concurrency (planHash/revision), rollback, and
  fresh node ids;
- reloads the Builder through its existing loading path (the bridge posts
  `page-builder:template-imported`).

```
Toolbar "Use Templates" → page-only picker → apply dialog (counts + confirm)
        → FullPageImportService::import() → Builder re-reads the fresh draft
```

The Builder owns the mutation; the library only returns the chosen template.

---

## 3. Design Library destination workflow (admin)

Every `kind=page` card shows **Preview**, **Use This Template**, and **Export**
(no Insert). In admin (non-picker) mode the primary action carries
`data-design-library-use-template` and opens the destination dialog
(`destination-dialog.blade.php`, driven by `page-template.js`):

```
        Where would you like to use this template?

   Create New          |   Use Existing
     ○ Page             |     ○ Page
     ○ Post             |     ○ Post
```

### 3a. Create New

```
Template → create Draft Page/Post → apply template → redirect to Builder
```

`POST page-builder/library/template/create-apply` →
`TemplateApplicationService::createAndApply()`:

1. Guard: supported owner type, resource exists, resource is `kind=page`.
2. In one DB transaction: `ContentManager::create(['type', 'title', 'status' => 'draft'])`
   then `FullPageImportService::import()` into the new draft.
3. If the apply fails, the transaction **rolls back the just-created content** —
   no empty/half-applied page survives.
4. On success, redirect to the Builder editor (`EditorUrls::structure()`).

The new document is a **Draft**; nothing is ever auto-published.

### 3b. Use Existing

```
Template → search target → review counts → destructive confirm → apply → redirect
```

`GET page-builder/library/template/destinations?ownerType=&q=` searches **only**
the selected owner type. After a target is chosen, the client calls the existing
`import/plan` endpoint for counts, requires a destructive confirmation, then
`POST page-builder/library/template/apply-existing` →
`TemplateApplicationService::applyToExisting()`, which calls
`FullPageImportService::import()` with the plan hash + revision. The recovery
snapshot and draft-only behavior come straight from the Full Page importer.

---

## 4. Draft / published guarantees

- Application only ever writes the Builder **draft** (`LayoutStore::saveDraft`).
- Published content, SEO, slug, and title are untouched until the user publishes.
- Create New produces a **Draft** document; no auto-publish.
- A recovery snapshot is created for existing targets by `FullPageImportService`.

---

## 5. Authorization & validation

| Check | Where |
| --- | --- |
| `page-builder.edit` | `PageTemplateController::canContent()` |
| `pages.create` / `posts.create` (Create New) | `createApply` |
| `pages.edit` / `posts.edit` (Use Existing + search) | `applyExisting`, `destinations` |
| Entitlement / FREE-PRO lock (Phase 26.45) | `ensureImportable()` → 403 |
| Supported owner type | service guard → `unsupported_owner` |
| Destination exists | service guard → `destination_not_found` |
| Page Template kind only | service guard → `wrong_kind` |
| Compatibility valid at commit; stale plan/revision | `FullPageImportService` → `stale_revision` / `plan_mismatch` |

The RID is always resolved server-side through the repository; the client never
sends a Builder payload.

---

## 6. Failure & rollback behavior

- **Create New**: create + apply run in one DB transaction. A failed apply throws
  internally so the transaction rolls back the new content — the destination is
  never left as an empty or half-applied page, and nothing is published.
- **Use Existing**: a stale plan/revision or any blocker aborts the import with
  issues and leaves the existing draft **untouched** (the Full Page importer's
  atomic guarantee). No redirect is returned on failure.

---

## 7. Files

**New**

- `src/PageTemplate/TemplateApplicationService.php`
- `src/PageTemplate/TemplateApplicationOutcome.php`
- `src/PageTemplate/Exceptions/TemplateApplicationFailed.php`
- `src/Http/Controllers/PageTemplateController.php`
- `resources/views/design-library/destination-dialog.blade.php`
- `resources/js/design-library/page-template.js`

**Modified**

- `routes/admin.php` (4 template routes)
- `src/Builder/ResourcePicker/PickerContext.php` (label)
- `src/Http/Controllers/DesignLibraryController.php` (destination URLs)
- `resources/views/design-library/{card,header,index,import-dialog}.blade.php`
- `resources/views/builder/resource-picker/toolbar.blade.php`
- `resources/views/filament/pages/editor.blade.php` (comment)

Internal data hooks (`data-pb-import-design`, `data-design-library-import`) keep
their names to stay surgical; only user-facing labels changed.

---

## Deferred: portable media

Exporting a Page Template that carries media so it survives a move between
installations is **out of scope here** and intentionally not implemented. The
current, frozen boundary is preserved exactly:

- `.tncms-design` stays **schemaVersion 2**, a **single JSON artifact**, with the
  existing **2 MB** limit.
- **No** embedded binaries, **no** `media/` directory, **no** checksum migration,
  **no** remote downloader, **no** new package format.
- The forbidden media/storage keys are unchanged; relative and currently-safe
  URLs behave exactly as before.
- No claims of media portability are made.

> **Roadmap.** Portable media is reserved for a later dedicated phase:
> **Portable Design Package & Media Resolution Architecture**. It will decide the
> container format (e.g. a zip alongside — never replacing — the frozen single
> file), asset metadata + dedup, whether `cms_media` gains a checksum, and how
> assets route through the Media Manager (never direct filesystem writes). None of
> that is built or implied by this phase.
