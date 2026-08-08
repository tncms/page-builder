<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the Builder Resource Picker ES module (Phase 26.47B).
 *
 * The module is a thin host-side bridge: it opens the Design Library in Picker
 * mode inside an overlay iframe and relays the resource selection back to the
 * Builder. It performs no mutation itself and never touches Builder state — the
 * source file IS the served asset (no build step), mirroring the Design Library
 * enhancement script.
 */
final class BuilderResourcePickerController
{
    public function script(): Response
    {
        $path = dirname(__DIR__, 3).'/resources/js/builder/resource-picker/resource-picker.js';
        $js = is_file($path) ? (string) file_get_contents($path) : '';

        return response($js, 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
