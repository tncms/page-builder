<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Capture;

/**
 * Generates a captured resource's preview reference (Phase 26.48, Rule 4).
 *
 * It REUSES the existing Runtime Preview engine: the preview is the rid-keyed
 * Runtime Preview route (exactly like the Design Library / Inspector). It never
 * renders a screenshot and never introduces a second preview renderer — it only
 * records the route the existing engine already serves.
 */
final class DesignPreviewGenerator
{
    /**
     * @return array{previewUrl: string, thumbnail: null, generator: string}
     */
    public function generate(string $rid): array
    {
        $params = ['source' => 'rid', 'ref' => $rid];

        try {
            $url = route('page-builder.preview', $params);
        } catch (\Throwable) {
            $url = url('page-builder/preview').'?'.http_build_query($params);
        }

        return [
            'previewUrl' => $url,
            'thumbnail' => null,
            'generator' => 'runtime-preview',
        ];
    }
}
