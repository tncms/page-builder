<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Plugins\PageBuilder\PageBuilderServiceProvider;

// Core-compatibility fail-closed (PB RC-B). Core loads this route file in a pass
// that is INDEPENDENT of the service provider's registration, so a provider that
// threw on an unsupported Core would otherwise still leave the plugin's routes
// registered. Consult the single boot-compat decision and register nothing when
// the running Core is below the minimum, so an incompatible Core leaves no
// routable surface behind.
if (PageBuilderServiceProvider::compatibilityBlocksBoot()) {
    return;
}

/*
|--------------------------------------------------------------------------
| Page Builder routes (loaded by the core ExtensionManager)
|--------------------------------------------------------------------------
|
| The ExtensionManager wraps this file in the "web" middleware group and loads
| it BEFORE the CMS frontend catch-all, so plugin routes take precedence.
|
| The admin editor UI is served by the Filament panel (PageBuilderDashboardPage
| at /{admin}/page-builder), so there is no raw admin route here yet. Two seams
| are loaded for later phases:
|   - api.php   read-only status + future editor endpoints (api/page-builder/*)
|   - admin.php reserved for raw admin endpoints that shouldn't live in Filament
|
*/

Route::prefix((string) config('page-builder.api_prefix', 'api/page-builder'))
    ->name('page-builder.api.')
    ->group(__DIR__.'/api.php');

require __DIR__.'/admin.php';
