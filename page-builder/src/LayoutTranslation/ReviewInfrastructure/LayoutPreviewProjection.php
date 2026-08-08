<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure;

use Plugins\PageBuilder\LayoutTranslation\Correspondence\NodeCorrespondence;
use Plugins\PageBuilder\LayoutTranslation\Diff\NodeChangeType;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationStateLoaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Review\Contracts\PreviewProjectionInterface;
use Plugins\PageBuilder\LayoutTranslation\Review\Preview\PreviewAvailability;
use Plugins\PageBuilder\LayoutTranslation\Review\Preview\PreviewFragment;
use Plugins\PageBuilder\LayoutTranslation\Review\Preview\PreviewSide;
use Throwable;

/**
 * Production {@see PreviewProjectionInterface}: renders an origin's B/S/T node for review (LT-8D).
 *
 * Layer: Translation (review infrastructure). It reads canonical state LOCK-FREE (LT-7 loader `load()`,
 * never `loadForUpdate`) and locates the node by correspondence origin (LT-4) in each document, rendering
 * a concise, read-only content projection. Content crosses one-directionally, to the screen only
 * (INV-LT-8A-02). It classifies nothing — the `change` label is a non-authoritative placeholder; the plan
 * remains the authority. Fail-soft: an unbound target or a read failure yields an unavailable fragment;
 * a missing side or node simply renders blank. Baseline content is best-effort via the same seam the
 * provider uses (null until baseline documents are persisted).
 */
final class LayoutPreviewProjection implements PreviewProjectionInterface
{
    public function __construct(
        private readonly SynchronizationStateLoaderInterface $loader,
        private readonly BaselineDocumentSourceInterface $baselineDocuments,
    ) {}

    public function fragmentFor(PageTranslationKey $target, string $origin): PreviewFragment
    {
        try {
            $state = $this->loader->load($target);
        } catch (Throwable) {
            return PreviewFragment::unavailable($origin, NodeChangeType::ContentUpdated, PreviewAvailability::NotBound);
        }

        if (! $state->isBound()) {
            return PreviewFragment::unavailable($origin, NodeChangeType::ContentUpdated, PreviewAvailability::NotBound);
        }

        $baselineDoc = $this->baselineDocuments->documentFor($target);

        // `change` is labelling-only and non-authoritative (the plan classifies); sides may be null.
        return new PreviewFragment(
            $origin,
            NodeChangeType::ContentUpdated,
            PreviewAvailability::Available,
            $this->renderNode($baselineDoc, $origin),
            $this->renderNode($state->hasSource() ? $state->sourcePayload() : null, $origin),
            $this->renderNode($state->hasTarget() ? $state->targetPayload() : null, $origin),
        );
    }

    /**
     * @param  array<string, mixed>|null  $document
     */
    private function renderNode(?array $document, string $origin): ?PreviewSide
    {
        if ($document === null) {
            return null;
        }

        $node = $this->locate($document, $origin);
        if ($node === null) {
            return null;
        }

        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        unset($data['blocks'], $data[NodeCorrespondence::ORIGIN_KEY]);

        $render = json_encode(
            ['type' => (string) ($node['type'] ?? ''), 'data' => $data],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return new PreviewSide($render === false ? '' : $render);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>|null
     */
    private function locate(array $document, string $origin): ?array
    {
        $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
        foreach ($sections as $section) {
            if (is_array($section)) {
                $found = $this->search($section, true, $origin);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function search(array $node, bool $isSection, string $origin): ?array
    {
        if (NodeCorrespondence::originOf($node) === $origin) {
            return $node;
        }

        $children = $isSection
            ? (is_array($node['blocks'] ?? null) ? $node['blocks'] : [])
            : (is_array($node['data']['blocks'] ?? null) ? $node['data']['blocks'] : []);

        foreach ($children as $child) {
            if (is_array($child)) {
                $found = $this->search($child, false, $origin);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
