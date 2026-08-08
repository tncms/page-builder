# Full Page Template Import (Phase 26.44)

> **Status:** Implemented. Transactional, draft-only, runtime-safe. Resolves
> resources **only** through the Design Resource Repository (26.42) and reuses the
> Runtime Preview (26.41) for confirmation. Changes no serialization,
> `BuilderState`, `CommandBus`, or the Extension API.

---

## 1. What it does

Imports a **Full Page Template** (`kind: page`) into an existing document's Builder
**draft**, replacing the draft's content in one atomic step. The user then edits,
saves, and publishes through the normal flow.

Guarantees:
- **Draft only** — the published payload, editor mode, SEO, slug, and title are
  never touched.
- **Atomic** — any failure leaves the original draft byte-identical (a recovery
  snapshot is taken and the write happens inside a DB transaction).
- **Copy-on-import** — the imported tree is a deep copy with **fresh, unique node
  ids**; it has no live link to the source resource.
- **Repository-only** — the RID is resolved server-side; the client never sends a
  builder payload.

---

## 2. Workflow

```
Design Library (page card)
   └─ Insert → (kind=page, target set) → Import dialog
        1. POST /library/import/plan   → dry run (read-only) → plan + warnings/blockers
        2. user confirms
        3. POST /library/import        → atomic commit → result
             ├─ snapshot old draft
             ├─ fresh node ids
             ├─ saveDraft() once (draft only)
             ├─ verify counts + unique ids
             └─ success | rollback (draft unchanged)
```

Block/section resources continue to use the existing insert intent
(`page-builder:insert-resource`); only pages use the import workflow.

---

## 3. Dry run (`FullPageImportService::plan`)

Deterministic, read-only, mutation-free. It resolves the resource and reports:

| Field | Meaning |
|---|---|
| `ok` | no blockers |
| counts | current vs incoming sections/blocks |
| `compatibility` | schema version, builder range, required components/features, missing components |
| `dependencies` | referenced components/members and whether each resolves |
| `assets` | safe asset URLs referenced by the template |
| `tokens` | design-token references found (resolved at render with fallbacks) |
| `warnings` | non-blocking (e.g. unverified schema/feature/token) |
| `blockers` | blocking (see §5) |
| `planHash` | hash of the id-free incoming tree (+rid+version) |
| `revision` | hash of the current draft (optimistic-concurrency token) |

The plan contains no timestamps or randomness, so the same inputs always produce
the same `planHash`/`revision`.

---

## 4. Commit (`FullPageImportService::import`)

1. Re-run the plan; abort with its blockers if not `ok`.
2. If `expectedPlanHash` is supplied and differs → **`plan_mismatch`** (the template
   changed since preview).
3. If `expectedRevision` is supplied and differs → **`stale_revision`** (the draft
   changed since preview).
4. In a DB transaction:
   - re-read the draft and re-check the revision (throws to roll back if stale);
   - **snapshot** the old draft;
   - resolve the resource again, normalize, assign **fresh node ids**;
   - `saveDraft()` once (draft only);
   - **verify** section count + unique ids (throws to roll back on mismatch).
5. Any exception rolls the transaction back → the draft is unchanged → a
   `FullPageImportResult` with blockers is returned.

---

## 5. Validation (blockers)

| Code | Cause |
|---|---|
| `resource_not_found` | RID does not resolve in the repository |
| `wrong_kind` | resource is not a `page` |
| `target_not_found` | owner type unsupported or the document is missing |
| `empty_template` | template has no sections |
| `schema_mismatch` | payload schema is not `pagebuilder/01` |
| `unknown_components` | template uses components not installed (checked against the registries) |
| `unsafe_asset` | an asset URL uses an unsafe scheme (`isSafeAssetUrl` policy) |
| `missing_dependency` | a referenced member resource is missing |
| `plan_mismatch` / `stale_revision` | confirmation no longer matches (§4) |
| `verify_failed` / `commit_failed` | post-write verification or transaction failure (rolled back) |

Warnings (non-blocking): unverified tree schema version, unverifiable required
feature, unresolved design tokens (they render via fallbacks).

---

## 6. Files

| File | Role |
|---|---|
| `src/Import/FullPageImportService.php` | Dry run + atomic commit. |
| `src/Import/FullPageImportRequest.php` | Immutable request (server resolves the RID). |
| `src/Import/FullPageImportPlan.php` | Dry-run result. |
| `src/Import/FullPageImportResult.php` | Commit result. |
| `src/Import/FullPageImportIssue.php` | Blocker/warning. |
| `src/Import/FullPageImportSnapshot.php` | Recovery snapshot of the old draft. |
| `src/Import/NodeIdGenerator.php` | Fresh unique node ids. |
| `src/Http/Controllers/FullPageImportController.php` | `plan` + `commit` endpoints (guarded). |
| routes `page-builder.library.import.plan` / `.import` | In `routes/admin.php` (POST). |
| Design Library dialog + JS import workflow | In `resources/views/design-library/` + `resources/js/design-library/`. |

The Design Library is opened with `?ownerType=&ownerId=` to set the import target;
without a valid target, page templates cannot be imported (the workflow is hidden).

---

## 7. What it does NOT do (frozen)

- No serialization / `BuilderState` / `CommandBus` / Extension-API change — import is
  a persistence operation via `LayoutStore::saveDraft()`.
- No auto-publish; no SEO/slug/title changes; no editor-mode change.
- No live link to the source (copy-on-import); no client-supplied payload.

---

## 8. Tests

`tests/Feature/FullPageImport/` (12 tests): dry-run ok/determinism/read-only,
rejection of non-page/unknown-resource/bad-target, atomic replace with fresh unique
ids, published-unchanged, copy-on-import + fresh ids per import, stale-revision and
plan-mismatch aborts (draft preserved), non-page import leaves draft unchanged, and
endpoint permission guards.

> **Local test note:** move `storage/app/tncms-installed` aside while running plugin
> tests (see [runtime-preview.md §10](runtime-preview.md)); CI runs marker-free.

```bash
php artisan test tests/Feature/FullPageImport
```

---

*End of full-page-template-import.md*
