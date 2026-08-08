# LT-2 — Migration Plan

**Status: Migration CREATED, NOT EXECUTED.** Run only when explicitly requested.

## Migration

`plugins/page-builder/database/migrations/2026_07_20_000001_create_pb_layout_translation_bindings_table.php`
— creates `pb_layout_translation_bindings` only. Follows the existing plugin convention
(`{date}_{seq}_create_{table}_table.php`). Loaded for the active plugin by the CMS ExtensionManager; run
explicitly in tests via `--path plugins/page-builder/database/migrations`.

## Safety properties

- **Additive** — creates one new table; touches nothing else.
- **No `pb_layouts` change** — no locale column, no data rewrite, no deletion.
- **No Core table modification.**
- **Reversible** — `down()` drops only the additive table.
- **Conservative FK** — `layout_id → pb_layouts.id` `restrictOnDelete` (no cascade).
- **Ordering** — filename-ordered after `2026_07_06_000001_create_pb_layouts_table.php`, so the FK
  target exists first.

## Execution

Not executed by this phase. To apply (when requested):

```
php artisan migrate --path plugins/page-builder/database/migrations
```

Rollback:

```
php artisan migrate:rollback --path plugins/page-builder/database/migrations
```

## Backfill (separate, application-level, explicit)

Schema creation is **separate** from data reconciliation. After migrating, an operator may run the
additive, fail-closed, idempotent backfill:

```
php artisan page-builder:layout-translation:backfill
```

It binds only default-locale page translations whose canonical identity and owning content resolve
through existing public contracts; it never fabricates identity, never overwrites, and reports
unresolved records. It is never run automatically and never executes inside a migration.
