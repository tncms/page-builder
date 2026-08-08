<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Preview;

use Plugins\PageBuilder\Support\ColumnWidthRegistry;
use Plugins\PageBuilder\Support\DynamicStyle\DynamicStyleRegistry;
use Plugins\PageBuilder\Support\PageBuilderFrontendAssets;
use Plugins\PageBuilder\Support\PageBuilderManager;
use Throwable;

/**
 * Renders a Design Resource for read-only preview using the EXACT production
 * rendering pipeline — no forked renderer, no duplicated asset logic.
 *
 * The sequence mirrors FrontendRenderer (the public frontend path):
 *   1. reset the runtime CSS collectors (column widths + dynamic styles)
 *   2. render the payload via PageBuilderManager::render() in PUBLIC mode
 *      (no editor markers, no selection, no interactivity)
 *   3. read the builder's runtime asset head via PageBuilderFrontendAssets
 *
 * Guarantees:
 *   - Runtime-safe: touches no serialization, BuilderState, CommandBus, or the
 *     Extension API. It only *reads* through public rendering services.
 *   - Deterministic: identical contexts produce byte-identical output; results
 *     are memoised in an in-memory (non-persistent) cache.
 *   - Never reads the current page / editor state: it renders only the resource
 *     tree carried by the PreviewContext.
 */
final class PreviewEngine
{
    public function __construct(
        private readonly PageBuilderManager $manager,
        private readonly PageBuilderFrontendAssets $assets,
        private readonly ColumnWidthRegistry $columnWidths,
        private readonly DynamicStyleRegistry $dynamicStyles,
        private readonly SampleContentResolver $sample,
        private readonly DevicePresets $devices,
        private readonly PreviewCache $cache,
    ) {
    }

    public function preview(PreviewContext $context): PreviewResponse
    {
        return $this->cache->remember($context->cacheKey(), fn (): PreviewResponse => $this->build($context));
    }

    private function build(PreviewContext $context): PreviewResponse
    {
        $device = $this->devices->normalize($context->device);
        $width = $this->devices->width($device);

        $payload = $context->payload;
        if ($context->sampleContent) {
            $payload = $this->sample->apply($payload);
        }

        // Mirror the public frontend render exactly: reset runtime CSS collectors,
        // force production (non-editor) mode, render, then read the asset head.
        $this->columnWidths->reset();
        $this->dynamicStyles->reset();
        $this->manager->runtime()->setEditorMode(false);

        try {
            $body = $this->manager->render($payload);
        } catch (Throwable $e) {
            report($e);
            $body = '';
        }

        $head = $this->assets->headHtml();

        return new PreviewResponse(
            kind: $context->kind,
            device: $device,
            width: $width,
            locale: $context->locale,
            head: $head,
            body: $body,
            hash: $context->cacheKey(),
        );
    }
}
