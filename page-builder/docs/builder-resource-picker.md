# Builder Resource Picker

_Phase 26.47B — the Design Library as the Builder's context-aware resource picker._

The Design Library is both an admin browser (Phase 26.47A) and — in **Picker
mode** — the Builder's resource picker. The Builder provides the context; the
Design Library provides the resources; **the Builder owns every mutation**. The
library never inserts, imports, or modifies Builder state directly.

```
Builder → Open Design Picker → Design Library (repository) → Preview
        → User selects → Builder executes the action
```

## Design

The integration is a **sibling bridge** injected into the editor page. It does not
touch the Vue editor bundle, BuilderState, the CommandBus, serialization, or the
Extension API — those are unchanged.

| Piece | Location | Role |
| --- | --- | --- |
| `PickerMode` | `src/Builder/ResourcePicker/PickerMode.php` | admin vs picker |
| `PickerContext` | `src/Builder/ResourcePicker/PickerContext.php` | context → resource kind (+ owner for pages) |
| `BuilderResourcePicker` | `src/Builder/ResourcePicker/BuilderResourcePicker.php` | builds the Picker-mode Design Library URL |
| Bridge script | `resources/js/builder/resource-picker/resource-picker.js` | overlay iframe + selection relay |
| Toolbar partial | `resources/views/builder/resource-picker/toolbar.blade.php` | "Import Design" button + config + script |
| Picker mode views | `resources/views/design-library/*` | hide admin actions when `isPicker` |

## Context model (Rule 2)

A Builder context maps to exactly one resource kind — unrelated kinds are never
shown:

| Builder context | Resource kind | Primary action | Owner required |
| --- | --- | --- | --- |
| Page editor | `page` | Import Design | yes (import target) |
| Insert Section | `section` | Insert Section | no |
| Insert Block | `block` | Insert Block | no |
| Insert Kit | `kit` | _reserved_ | — |

`BuilderResourcePicker::pickerUrl()` reuses the existing `page-builder.library`
route with `kind`, `mode=picker`, and (pages only) `ownerType`/`ownerId`.

## Picker mode (Rule 8)

Same repository, same cards, same Runtime Preview — different actions:

- **Admin**: preview, export, package import, metadata.
- **Picker**: preview + one context action.

In Picker mode the Design Library additionally locks the kind (no discovery tabs,
no kind selector) and hides the package import + per-card export.

## Workflow

### Full page (`Import Design`)

1. The editor toolbar "Import Design" opens the Design Library scoped to `page`
   with the owner document.
2. Selecting a page shows the replace confirmation (current vs incoming sections
   and blocks) and imports via **`FullPageImportService`** — draft only; published
   content is untouched until publish.
3. On success the Design Library posts `page-builder:template-imported`; the bridge
   reloads the Builder, which re-reads the freshly-saved draft.

### Section / Block

1. The Builder opens the picker via
   `window.TNPageBuilderResourcePicker.open('section' | 'block')`.
2. Selecting a resource posts `page-builder:insert-resource` `{ rid, kind }`.
3. The bridge re-dispatches it as a decoupled `CustomEvent` on `document`. **The
   Builder owns the insertion** — the bridge performs no mutation.

## Permissions

Reuses `cms_can('page-builder.edit')`. No new permissions. The import endpoints
enforce it server-side (and the FREE/PRO boundary).

## What did not change

Runtime, Repository, Runtime Preview, Package Sharing, the admin Design Library
(default mode), BuilderState, CommandBus, serialization, and the Extension API.
