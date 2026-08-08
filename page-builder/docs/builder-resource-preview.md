# Builder Resource Preview & Inspector

_Phase 26.47F — a preview-first Inspector for Builder Resources. UI/UX only._

Clicking a design resource in the palette no longer inserts it blindly — it opens
the **Builder Resource Inspector**, which previews the resource (via the existing
Runtime Preview engine) and inserts on confirmation through the **existing**
insertion pipeline. Nothing in the Builder runtime, the registry, the repository,
the insertion commands, BuilderState, or the CommandBus is changed.

Core principle: **preview first, insert second.**

## Component architecture

All new, under `resources/editor/inspector/`:

| Component | Responsibility |
| --- | --- |
| `BuilderResourceInspector.vue` | The drawer: focus trap, Esc/Enter, ←/→ nav, responsive layout, composes the rest |
| `ResourcePreview.vue` | Large preview via the Runtime Preview iframe + device switch (lazy) |
| `ResourceMetadata.vue` | Category / Version / Tags / Description — hides empty, never shows the RID |
| `ResourceBadges.vue` | Type badge (SECTION / BLOCK / KIT) + source badge (Built-in / Imported; others reserved) |
| `ResourceCompatibility.vue` | Compatible / Requires newer / Unsupported / Unknown from existing metadata |
| `ResourceActions.vue` | Cancel + Insert (Insert disabled when locked); reserved actions omitted |

The palette (`EditorLeftPanel.vue`) renders each resource as a preview card; a
click calls `openInspector(group, item)`. The Inspector is a controlled child:
`@insert` → `store.insertBuilderResource(item)` (26.47E), `@close`,
`@update:index` for prev/next.

## Preview (Rule 4)

`ResourcePreview` embeds an iframe pointed at the **existing** Runtime Preview
route (Phase 26.41), keyed by the resource RID exactly as the Design Library does:

```
<resourcePreview>?source=rid&ref=<rid>&device=<desktop|tablet|mobile>
```

The route base is added to the editor boot as `boot.api.resourcePreview`
(`url('page-builder/preview')`) — **no new endpoint, no new renderer, no
screenshot**. It is lazy (Rule 14): the iframe exists only while the Inspector is
open and its `src` changes per resource, so previews are never preloaded for the
whole palette.

## Metadata (Rule 5)

Shown from the **registry cache** (the palette item — no refetch): name (title),
category, version, tags, type, source, compatibility. Empty fields are hidden and
the **RID is never displayed**. Description is rendered only when present; the
registry does not expose it today (and is not modified here), so it is hidden — an
optional `description?` field is reserved on the item type so it appears
automatically if a future additive registry field provides it.

## Compatibility (Rule 8)

Derived from the existing `compatibility` metadata with **no new version logic**:
metadata present → **Compatible**, absent → **Unknown**. The **Requires newer** /
**Unsupported** states render only if the metadata carries an explicit `status`
flag (server-driven, future). Registry resources are schema-validated at import,
so in practice they resolve to Compatible.

## Availability (Rule 9)

Reuses Phase 26.45: a locked resource shows a disabled **🔒 Locked** control and
cannot be inserted (Enter is ignored for it). Insertion is never bypassed.

## Insertion (Rule 10)

The Insert action emits `@insert`; the palette routes it to
`store.insertBuilderResource(item)` — the same 26.47E path (`insert-pattern` /
`insert-component`, server-side id regeneration, one undo). The Inspector owns no
insertion logic, no commands, and no runtime.

## Keyboard & accessibility (Rules 11, 15)

- **Esc** closes; **Enter** inserts when available; **←/→** navigate the group.
- `role="dialog"`, `aria-modal="true"`, `aria-label` with the resource name.
- Focus is moved into the drawer on open, **trapped** on Tab, and restored to the
  triggering element on close. Nav/close/actions carry `aria-label`s.

## Navigation (Rule 12)

Prev/Next move through the resources of the **clicked resource's palette group**
(flattened across its categories) without reloading the Builder — the Inspector
just re-renders for the new index.

## Responsive (Rule 13)

A right-side drawer on desktop/tablet that becomes a full-screen drawer at ≤ 640px.
The preview device switch (Desktop/Tablet/Mobile) constrains the iframe width.

## Visual consistency (Rule 17)

Built from the existing editor tokens (`--pb-accent`, `--pb-border`,
`--pb-editor-surface`, `--pb-editor-text`, `--pb-editor-text-muted`, `--pb-panel`,
`--pb-radius`), mirroring the editor's existing dialog convention
(`role="dialog"`, `@click.self` backdrop, scoped styles).

## Reserved (Rules 16, 19)

Favorite / Save / Copy / Open Marketplace / View Updates / Share are **not**
rendered. Save as Design, My Designs, Community, Marketplace, and PRO are **not**
implemented — only preview, inspection, and insertion.

## Files

**New:** `resources/editor/inspector/{BuilderResourceInspector,ResourcePreview,ResourceMetadata,ResourceBadges,ResourceCompatibility,ResourceActions}.vue`, this doc, `resources/editor/inspector/__tests__/BuilderResourceInspector.test.ts`.

**Modified:** `resources/editor/components/EditorLeftPanel.vue` (click → Inspector),
`resources/editor/types.ts` (`resourcePreview` on boot api; optional `description`),
`resources/views/filament/pages/editor.blade.php` (boot `resourcePreview` URL — the
one PHP touch, reusing the existing preview route).

## What did not change

Builder runtime, Builder Resource Registry, repository, insertion commands,
BuilderState, CommandBus, Runtime Preview engine, Page Template Application,
Package Sharing, and the Extension API — all unchanged.
