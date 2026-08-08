# Design Library — Admin Experience

_Phase 26.47A — Admin migration & resource discovery UX._

The admin surface for reusable design assets is the **Design Library**. It replaces
the former **Template Library** navigation entry and presents **Design Resources**
(blocks, sections, full pages) rather than templates.

This phase is a UX migration only. It adds no runtime capability, no new
architecture, and no duplicate systems. Everything visible comes from the
`DesignResourceRepository`; previews render through the Runtime Preview Engine.

## Where it lives

| Concern | Location |
| --- | --- |
| Navigation entry | `Plugins → Design Library` (`DesignLibraryPage`) |
| Repository-backed page | `GET page-builder/library` (`DesignLibraryController@index`) |
| Enhancement module | `resources/js/design-library/design-library.js` |
| Package import module | `resources/js/design-library/package-import.js` |

The Filament nav item (`DesignLibraryPage`) is a gateway: its `getNavigationUrl()`
opens the repository-backed page directly, so users land on the resource-discovery
surface. Reaching the Filament slug directly forwards to the same destination —
there is a single Design Library screen.

## Views

The page is composed from small partials under
`resources/views/design-library/`:

| Partial | Responsibility |
| --- | --- |
| `index.blade.php` | Document shell, filters, layout, modals |
| `header.blade.php` | Title, subheading, live count, Import Design action |
| `tabs.blade.php` | Discovery tabs for the canonical kinds |
| `card.blade.php` | A single Design Resource card |
| `availability-badge.blade.php` | FREE/PRO availability pill |
| `import-dialog.blade.php` | `.tncms-design` package import dialog |

## Discovery

Primary discovery tabs expose the canonical `DesignResource` kinds and preserve the
other active filters (they work without JavaScript, via query parameters):

```
All Designs · Pages · Sections · Blocks · Kits
```

Search and the facet filters (kind, category, source, vendor, tag) operate across
all resource kinds via `ResourceQuery` on the repository. No folder scanning, no
package reads, no second resource list.

## Cards

Each card shows the resource preview, name, kind, category, source/vendor, tags,
version, availability, and — for pages — the section count. The primary action is
chosen by kind:

| Kind | Primary action |
| --- | --- |
| `page` | **Import Design** (draft-only full-page import) |
| `section` | **Insert Section** |
| `block` | **Insert Block** |
| `kit` | **Insert Kit** |

Locked (entitlement-gated) resources stay listed and previewable; only the
import/insert action is gated. Preview always uses the Runtime Preview Engine.

## Empty state

When no resources match the active filters the page shows:

> No design resources match your filters.
> Try clearing the search, choosing a different kind, or importing a design.

## Future hooks

The information architecture leaves room for future, non-FREE surfaces — **My
Designs**, **Community**, **Marketplace**, and **PRO Resources**. These are not
implemented and are intentionally not surfaced as actions; they remain seams to
avoid dead buttons.

## What did not change

Runtime, serialization, BuilderState, the CommandBus, the Extension API, the
`DesignResourceRepository`, the Runtime Preview Engine, Full Page Import, the
FREE/PRO boundary, and Package Sharing are all untouched. The former Vue
Template-Library workspace modules under `resources/editor/templates/workspace/`
are no longer mounted by the admin; they are retained but orphaned.
