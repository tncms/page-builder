<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Plugins\PageBuilder\Facades\PageBuilder;
use Plugins\PageBuilder\Http\Controllers\ComponentPreviewController;
use Plugins\PageBuilder\Http\Controllers\EditorContextController;
use Plugins\PageBuilder\Http\Controllers\EditorMetadataController;
use Plugins\PageBuilder\Http\Controllers\EditorPreviewController;
use Plugins\PageBuilder\Http\Controllers\PatternController;
use Plugins\PageBuilder\Http\Controllers\ResourceCatalogController;
use Plugins\PageBuilder\Http\Controllers\StateCommandController;

/*
|--------------------------------------------------------------------------
| Page Builder API routes
|--------------------------------------------------------------------------
|
| Prefixed with `api/page-builder` and named `page-builder.api.*` by web.php.
| A read-only status endpoint, the Phase 6 editor-metadata endpoint, and the
| Phase 8 stateless command endpoints the future Vue editor uses to mutate a
| layout through the CommandBus instead of editing raw JSON. The metadata and
| state endpoints require the page-builder.edit permission.
|
*/

Route::get('status', function () {
    return response()->json([
        'success' => true,
        'data' => PageBuilder::status(),
    ]);
})->name('status');

Route::get('editor/metadata', EditorMetadataController::class)->name('editor.metadata');

// PB-TEMPLATE-REGISTRY-2-B — unified Resource Browser catalog (metadata only, lazy).
// Section resources are patterns, page resources are templates; labels resolve for the
// Admin UI locale. Requires the page-builder.edit permission (enforced in the controller).
Route::get('editor/resource-catalog', ResourceCatalogController::class)->name('editor.resource-catalog');

// Phase 9 — Vue editor shell: owner-scoped bootstrap context + draft / publish.
Route::get('editor/context/{ownerType}/{ownerId}', [EditorContextController::class, 'show'])
    ->name('editor.context');
Route::post('editor/context/{ownerType}/{ownerId}/draft', [EditorContextController::class, 'saveDraft'])
    ->name('editor.context.draft');
// PB-TREG-2 — mint-at-insert orchestration: insert a registered resource by dispatching
// the existing insert commands, then mint provenance from the authoritative result. It
// persists nothing (saveDraft remains the only persistence authority) and returns
// no-store. It is NOT a second insertion authority — it delegates to the one command bus.
Route::post('editor/context/{ownerType}/{ownerId}/insert-registered-resource', [EditorContextController::class, 'insertRegisteredResource'])
    ->name('editor.context.insert-registered-resource');
Route::post('editor/context/{ownerType}/{ownerId}/publish', [EditorContextController::class, 'publish'])
    ->name('editor.context.publish');

// Phase 12 — visual canvas: render the current payload to HTML with editor markers.
Route::post('editor/preview', EditorPreviewController::class)->name('editor.preview');

// Phase 21 — Live Component Runtime: render ONE component (by id) with editor
// markers so the frontend Live Builder can reconcile it without a full swap.
Route::post('editor/component-preview', ComponentPreviewController::class)->name('editor.component-preview');

// Phase 25 — Pattern Library: list manifests + categories, fetch one pattern's
// payload (lazy), and stream pattern thumbnails. Requires page-builder.edit.
Route::get('patterns', [PatternController::class, 'index'])->name('patterns.index');
// Phase 25.2 — pattern packs (must precede patterns/{id} so it is not swallowed).
Route::get('patterns/packs', [PatternController::class, 'packs'])->name('patterns.packs');
Route::get('patterns/{id}', [PatternController::class, 'show'])->name('patterns.show');
Route::get('patterns/{id}/thumbnail', [PatternController::class, 'thumbnail'])->name('patterns.thumbnail');
// Phase 25.1 — resolve a pattern's variables against its payload template.
Route::post('patterns/{id}/resolve', [PatternController::class, 'resolve'])->name('patterns.resolve');

// Phase 8 — Builder State Engine: stateless command execution for the editor.
Route::post('state/command', [StateCommandController::class, 'command'])->name('state.command');
Route::post('state/transaction', [StateCommandController::class, 'transaction'])->name('state.transaction');

// Phase 26.47E — Builder Resource Registry: resolve a palette resource's id-free
// payload for native insertion via the existing insert-pattern / insert-component
// commands. Read-only; requires page-builder.edit + availability.
Route::get('builder/resources', [\Plugins\PageBuilder\Http\Controllers\BuilderResourceController::class, 'payload'])
    ->name('builder.resources');

// PB-TREG-2 — authenticated attestation lifecycle (POST only; CSRF via the web middleware
// group; page-builder.edit; no-store responses). Minting happens only at insert time
// (mint-at-insert, see editor.context.insert-registered-resource); these operate on an
// already-issued opaque group. The read-only catalog/resource GET above mints nothing.
Route::post('builder/attestation/{ownerType}/{ownerId}/renew', [\Plugins\PageBuilder\Http\Controllers\MaterializationController::class, 'renew'])
    ->name('builder.attestation.renew');
Route::post('builder/attestation/{ownerType}/{ownerId}/rebind', [\Plugins\PageBuilder\Http\Controllers\MaterializationController::class, 'rebind'])
    ->name('builder.attestation.rebind');

// Phase 26.48 — Save as Design: capture a Builder selection into a reusable design
// resource through the single Design Capture Pipeline. External persistence action
// (no CommandBus, no undo); requires page-builder.edit.
Route::post('builder/capture', [\Plugins\PageBuilder\Http\Controllers\DesignCaptureController::class, 'store'])
    ->name('builder.capture');
