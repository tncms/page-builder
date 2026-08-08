<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Plugins\PageBuilder\Package\DesignPackageExporter;
use Plugins\PageBuilder\Package\DesignPackageImporter;
use Plugins\PageBuilder\Package\Exceptions\PackageExportException;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;

/**
 * Export/import endpoints for portable `.tncms-design` packages (Phase 26.47).
 *
 *   GET  page-builder/library/export?rid=…        → download a package file
 *   POST page-builder/library/package/validate    → dry-run validation summary
 *   POST page-builder/library/package/import       → validate + persist (imported source)
 *
 * All transport is local file in / file out. Nothing here touches the runtime,
 * serialization, BuilderState, CommandBus, or the Extension API.
 */
final class DesignPackageController
{
    /** Package uploads are small JSON documents; cap the accepted size. */
    private const MAX_BYTES = 2_000_000;

    public function export(Request $request, DesignPackageExporter $exporter, DesignResourceRepositoryInterface $repository): Response
    {
        abort_unless($this->authorizedToView(), 403);

        $rid = (string) $request->query('rid', '');
        $resource = $rid !== '' ? $repository->tryFind($rid) : null;
        abort_if($resource === null, 404, 'Resource not found.');

        try {
            $manifest = $exporter->export($resource);
        } catch (PackageExportException $e) {
            abort(422, $e->getMessage());
        }

        return response($exporter->toJson($manifest), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$exporter->filenameFor($resource).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function validatePackage(Request $request, DesignPackageImporter $importer): JsonResponse
    {
        abort_unless($this->authorizedToEdit(), 403);

        $report = $importer->validate($this->packageContent($request));

        return response()->json(['success' => $report->isImportable(), 'data' => $report->toArray()]);
    }

    public function import(Request $request, DesignPackageImporter $importer): JsonResponse
    {
        abort_unless($this->authorizedToEdit(), 403);

        $report = $importer->import($this->packageContent($request));

        return response()->json(
            ['success' => $report->imported, 'data' => $report->toArray()],
            $report->imported ? 200 : 422,
        );
    }

    /**
     * Serves the package import/export enhancement module (no build step).
     */
    public function script(): Response
    {
        $path = dirname(__DIR__, 3).'/resources/js/design-library/package-import.js';
        $js = is_file($path) ? (string) file_get_contents($path) : '';

        return response($js, 200, [
            'Content-Type' => 'text/javascript; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    private function packageContent(Request $request): string
    {
        // Accept either an uploaded file or a raw JSON string in `content`.
        $file = $request->file('package');
        if ($file !== null && $file->isValid() && $file->getSize() <= self::MAX_BYTES) {
            return (string) file_get_contents($file->getRealPath());
        }

        $content = (string) $request->input('content', '');
        abort_if(strlen($content) > self::MAX_BYTES, 413, 'Package too large.');

        return $content;
    }

    private function authorizedToView(): bool
    {
        return function_exists('cms_can') && (cms_can('page-builder.view') || cms_can('page-builder.edit'));
    }

    private function authorizedToEdit(): bool
    {
        return function_exists('cms_can') && cms_can('page-builder.edit');
    }
}
