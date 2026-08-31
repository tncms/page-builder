<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Page Builder admin routes (reserved seam)
|--------------------------------------------------------------------------
|
| The Phase 1 admin surface is the Filament dashboard page
| (PageBuilderDashboardPage) mounted by the panel at /{admin}/page-builder, so
| there are no raw admin routes yet.
|
| This file exists as the documented extension point for future raw admin
| endpoints that don't belong inside the Filament page (e.g. editor autosave,
| structured-layout preview). Define them here guarded behind the admin
| middleware + the `page-builder.edit` / `page-builder.publish` permissions.
|
*/

// Phase 26.41 — Runtime Preview: serves the sandboxed read-only preview document
// for an iframe. Auth is enforced inside the controller via cms_can().
\Illuminate\Support\Facades\Route::get(
    'page-builder/preview',
    \Plugins\PageBuilder\Http\Controllers\PreviewController::class,
)->name('page-builder.preview');

// PB-DRAFT-PREVIEW-R1 — private, authenticated editor-document for the frontend
// editor iframe. Identity-based (ownerType/ownerId), so it never depends on the
// (unpublished) owner's public slug being routable. Auth (`page-builder.edit`) +
// locale validation are enforced inside the controller, which renders the DRAFT
// through Core's status-gate-free preview seam and stamps a private/noindex
// response. Never makes draft content public (INV-DRAFT-PREVIEW-01..10).
\Illuminate\Support\Facades\Route::get(
    'page-builder/{ownerType}/{ownerId}/editor-document',
    \Plugins\PageBuilder\Http\Controllers\EditorDocumentController::class,
)->name('page-builder.editor-document');

// Phase 26.43 — Design Library admin UI. Server-rendered from the Design Resource
// Repository; previews are served exclusively by the Runtime Preview route above.
\Illuminate\Support\Facades\Route::get(
    'page-builder/library',
    [\Plugins\PageBuilder\Http\Controllers\DesignLibraryController::class, 'index'],
)->name('page-builder.library');

\Illuminate\Support\Facades\Route::get(
    'page-builder/library/design-library.js',
    [\Plugins\PageBuilder\Http\Controllers\DesignLibraryController::class, 'script'],
)->name('page-builder.library.script');

// Phase 26.44 — Full Page Template import (draft only, atomic). RID resolved
// server-side via the repository; never accepts a client builder payload.
\Illuminate\Support\Facades\Route::post(
    'page-builder/library/import/plan',
    [\Plugins\PageBuilder\Http\Controllers\FullPageImportController::class, 'plan'],
)->name('page-builder.library.import.plan');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/import',
    [\Plugins\PageBuilder\Http\Controllers\FullPageImportController::class, 'commit'],
)->name('page-builder.library.import');

// Phase 26.47 — Design Package export/import (portable .tncms-design files).
// Export resolves the RID via the repository; import validates + persists into the
// `imported` source. Auth enforced inside the controller via cms_can().
\Illuminate\Support\Facades\Route::get(
    'page-builder/library/export',
    [\Plugins\PageBuilder\Http\Controllers\DesignPackageController::class, 'export'],
)->name('page-builder.library.export');

\Illuminate\Support\Facades\Route::get(
    'page-builder/library/package-import.js',
    [\Plugins\PageBuilder\Http\Controllers\DesignPackageController::class, 'script'],
)->name('page-builder.library.package.script');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/package/validate',
    [\Plugins\PageBuilder\Http\Controllers\DesignPackageController::class, 'validatePackage'],
)->name('page-builder.library.package.validate');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/package/import',
    [\Plugins\PageBuilder\Http\Controllers\DesignPackageController::class, 'import'],
)->name('page-builder.library.package.import');

// Phase 26.47B — Builder Resource Picker bridge script. Opens the Design Library in
// Picker mode inside the editor and relays the selection back to the Builder. Inert
// module (no mutation); the underlying import/select flows enforce their own auth.
\Illuminate\Support\Facades\Route::get(
    'page-builder/builder/resource-picker.js',
    [\Plugins\PageBuilder\Http\Controllers\BuilderResourcePickerController::class, 'script'],
)->name('page-builder.builder.resource-picker.script');

// Phase 26.47D — Page Template application ("Use This Template" destination
// workflow): create a Draft page/post or apply to an existing one, always through
// the (unchanged) FullPageImportService. Auth + entitlement enforced in the
// controller; application affects the Builder draft only.
\Illuminate\Support\Facades\Route::get(
    'page-builder/library/template.js',
    [\Plugins\PageBuilder\Http\Controllers\PageTemplateController::class, 'script'],
)->name('page-builder.library.template.script');

\Illuminate\Support\Facades\Route::get(
    'page-builder/library/template/destinations',
    [\Plugins\PageBuilder\Http\Controllers\PageTemplateController::class, 'destinations'],
)->name('page-builder.library.template.destinations');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/template/create-apply',
    [\Plugins\PageBuilder\Http\Controllers\PageTemplateController::class, 'createApply'],
)->name('page-builder.library.template.create-apply');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/template/apply-existing',
    [\Plugins\PageBuilder\Http\Controllers\PageTemplateController::class, 'applyExisting'],
)->name('page-builder.library.template.apply-existing');

// Phase 26.49 — "My Designs" management lifecycle inside the Design Library.
// Manages user-created Design Resources: metadata edit, archive/restore, soft
// delete, duplicate, and open-in-Builder (working copy). Payload / fingerprint /
// RID stay immutable — new content only ever enters via the Capture Pipeline.
// Ownership is verified server-side inside the manager; the coarse gate is
// `page-builder.edit`.
\Illuminate\Support\Facades\Route::get(
    'page-builder/library/my-designs',
    [\Plugins\PageBuilder\Http\Controllers\DesignManagementController::class, 'index'],
)->name('page-builder.library.my-designs');

\Illuminate\Support\Facades\Route::get(
    'page-builder/library/my-designs/manage.js',
    [\Plugins\PageBuilder\Http\Controllers\DesignManagementController::class, 'script'],
)->name('page-builder.library.my-designs.script');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/my-designs/metadata',
    [\Plugins\PageBuilder\Http\Controllers\DesignManagementController::class, 'metadata'],
)->name('page-builder.library.my-designs.metadata');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/my-designs/archive',
    [\Plugins\PageBuilder\Http\Controllers\DesignManagementController::class, 'archive'],
)->name('page-builder.library.my-designs.archive');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/my-designs/restore',
    [\Plugins\PageBuilder\Http\Controllers\DesignManagementController::class, 'restore'],
)->name('page-builder.library.my-designs.restore');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/my-designs/delete',
    [\Plugins\PageBuilder\Http\Controllers\DesignManagementController::class, 'delete'],
)->name('page-builder.library.my-designs.delete');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/my-designs/duplicate',
    [\Plugins\PageBuilder\Http\Controllers\DesignManagementController::class, 'duplicate'],
)->name('page-builder.library.my-designs.duplicate');

\Illuminate\Support\Facades\Route::post(
    'page-builder/library/my-designs/open',
    [\Plugins\PageBuilder\Http\Controllers\DesignManagementController::class, 'open'],
)->name('page-builder.library.my-designs.open');
