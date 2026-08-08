# Page Builder — Template Admin Entry + Import / Export

*Phase 26.35 (FREE v1). Adds the **admin entry** for the Phase 26.34 Template Library workspace and
wires **import/export** through the existing Phase 26.26 package + Phase 26.27 compatibility gate.
Everything stays read-only toward the runtime: browse + "Use" hand off to the standard apply flow;
import lands templates in the imported library. **No BuilderState, CommandBus, or runtime mutation.**
No marketplace, remote download, cloud, purchase, or licensing.*

```
Filament admin page (Plugins → Template Library)  ─mounts→  TemplateLibraryWorkspace.vue
   permission: page-builder.view                                 │
                                                     Export ─▶ resolve defs (26.24) → package (26.26) → download
                                                     Import ─▶ parse → validate + compat gate (26.26/26.27) → refresh
```

---

## 1. Admin entry (PHP / Filament)

`TemplateLibraryWorkspacePage` — a Filament page mirroring the dashboard page:

- Nav: **Plugins → Template Library**, slug `page-builder/templates`.
- Gated by `cms_can('page-builder.view')` on both `canAccess()` and `shouldRegisterNavigation()`.
- Registered in `plugin.json` → `filament.pages`.
- Blade `filament/pages/template-workspace.blade.php` renders a mount container
  `#pb-template-workspace`; the Vue workspace hydrates it via `mountTemplateWorkspace('#pb-template-workspace')`.

The page runs no BuilderState/command/runtime — it is purely the entry that hosts the workspace.

## 2. Export (reuses 26.26)

`exportWorkspaceTemplates(items, metadata)` resolves each library item's **definition** through the
Phase 26.24 source resolver (read-only), hands the definitions to `exportTemplatePackage` /
`serializeTemplatePackage`, and returns a `{ filename, json, count }` artifact. The UI performs the
browser download (Blob + anchor). Items whose definition can't be resolved are skipped; an empty
result is rejected (`reason: 'no-templates'`).

## 3. Import (reuses 26.26 + 26.27)

`importWorkspacePackage(json)` parses the uploaded JSON and runs the **existing atomic import**:
invalid schema, incompatible builder, unsafe asset URLs, and duplicate ids are all rejected there —
nothing is registered on failure. The thrown `TemplatePackageError` becomes a structured
`{ ok:false, error:{ message, issues } }` outcome the dialog renders. On success the templates land
in the imported source/library and the workspace refreshes (`store.load()`), so they appear
immediately and resolve/apply through the unchanged runtime.

The store exposes `exportResults(metadata)` / `importPackage(json)` / `clearTransfer()` with
transient `exportResult` / `importOutcome` state; `WorkspaceTransferBar.vue` provides the
export button, import file input, and success/error dialog.

---

## Boundaries

- **Entry only** — the admin page registers nav + route + permission; it hosts the Vue workspace.
- **Reuses existing package I/O** — no new import/export/validation logic; 26.26/26.27 unchanged.
- **Read-only toward the runtime** — no BuilderState, CommandBus, insert-pattern, or apply here.
- Invalid / incompatible / unsafe / duplicate packages are **rejected atomically** (nothing registered).
- No marketplace, remote download, cloud sync, purchase, or licensing. FREE v1.
