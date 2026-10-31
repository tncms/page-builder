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

        $inlineBefore = $this->inlineStyleKeys();

        try {
            $body = $this->manager->render($payload);
        } catch (Throwable $e) {
            report($e);
            $body = '';
        }

        $head = $this->assets->headHtml().$this->componentStyles($inlineBefore);

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

    /**
     * Identity of every inline style currently held by Core's asset registry, so the styles a component
     * registers WHILE this preview renders can be told apart from anything registered earlier.
     *
     * @return array<string, true>
     */
    private function inlineStyleKeys(): array
    {
        $keys = [];
        foreach ($this->inlineStyles() as $asset) {
            $keys[$asset->handle."\0".md5((string) $asset->code)] = true;
        }

        return $keys;
    }

    /**
     * PB-PREVIEW-PARITY-R1 — provider components style themselves through Core's `inline_style()` during render
     * (the public sanitizer strips `<style>`); the public page prints those through the theme head, but the
     * preview document only carried Page Builder's own asset head, so provider components previewed unstyled.
     *
     * Emits ONLY inline frontend head STYLES registered during this render. Never scripts, modules or
     * stylesheet links, and any style containing `<` (which could close the element) is dropped — the
     * preview stays inert under its unchanged `default-src 'none'` CSP.
     *
     * @param  array<string, true>  $before
     */
    private function componentStyles(array $before): string
    {
        $css = [];
        foreach ($this->inlineStyles() as $asset) {
            $code = (string) $asset->code;
            if (isset($before[$asset->handle."\0".md5($code)]) || str_contains($code, '<')) {
                continue;
            }
            $css[$asset->handle] = $code;
        }

        if ($css === []) {
            return '';
        }

        ksort($css, SORT_STRING);

        return "\n<style data-pb-preview-component-css>".implode("\n", $css).'</style>';
    }

    /** @return list<object> Core inline-style assets in frontend scope, head position (fail-soft: none) */
    private function inlineStyles(): array
    {
        if (! app()->bound('cms.assets')) {
            return [];
        }

        try {
            $out = [];
            foreach (app('cms.assets')->inlines() as $asset) {
                if (($asset->type ?? null) === 'inline_style'
                    && in_array($asset->scope ?? null, ['frontend', 'both'], true)
                    && ($asset->position ?? null) === 'head') {
                    $out[] = $asset;
                }
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }
}
