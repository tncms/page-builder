# LT-2 — Schema

**Status: IMPLEMENTED (LT-2). Additive table; `pb_layouts` unchanged.**

## Existing schema audit (`pb_layouts`, unchanged)

`id`, `owner_type`, `owner_id`, `editor_mode`, `draft_payload` (json), `published_payload` (json),
`draft_updated_by`, `published_by`, timestamps; `unique(owner_type, owner_id)`; no FK, no soft-delete,
**no locale column** (and none added). It remains the canonical layout-document store.

## New table — `pb_layout_translation_bindings`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `translation_group_key` | string, indexed | canonical `TranslationGroupKey` value |
| `page_translation_key` | string, **UNIQUE** | canonical `PageTranslationKey` value — the relationship owner |
| `layout_key` | string, **UNIQUE** | canonical `LayoutKey` value |
| `layout_id` | unsigned bigint, **FK → pb_layouts.id** | the bound layout document |
| `locale` | string(20), indexed | relationship **metadata / coherence only — NOT identity** |
| `existence_state` | string | `not_created` / `exists` / `invalid_reference` |
| `lifecycle_state` | string, **nullable** | `draft` / `published` / `archived`; null iff not `exists` |
| `synchronization_state` | string | `not_applicable` / `in_sync` / `source_changed` / `target_changed` / `diverged` / `conflicted` |
| `created_at`, `updated_at` | timestamps | |

Three **independent** state columns — never one flat `status` field.

## Constraints & indexes

- `UNIQUE(page_translation_key)` — a page translation owns at most one default layout.
- `UNIQUE(layout_key)` — a layout key resolves to exactly one binding.
- **No** `unique(translation_group_key, locale)` — preserves the future additive `LayoutKey` variant.
- Index on `translation_group_key`, `locale`, `layout_id`.
- **FK `layout_id → pb_layouts.id` with `restrictOnDelete`** (conservative, no cascade): a bound
  `pb_layouts` row cannot be deleted while the binding exists, so a translated layout can never be
  silently destroyed. Enforced on MySQL and on SQLite (project default `foreign_key_constraints=true`).

## Engine notes

Project default connection is SQLite (tests) with foreign keys enabled; production is MySQL-compatible.
`restrictOnDelete` and the string/unique/index definitions are portable across both.
