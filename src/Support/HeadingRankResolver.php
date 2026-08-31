<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * Centralized primary-heading authority (PB-FREE-LIBRARY-DESIGN-1-RC-B).
 *
 * Guarantees a rendered Page Builder document contains exactly ONE `<h1>`,
 * coordinated with the host theme's page-title visibility. The generic fact the
 * public Core page-title contract exposes (`show_page_title`, Core
 * >= 1.0.0-beta.7.1.23) is passed in through {@see FrontendRenderer} /
 * {@see FrontendEditorRenderer} and threaded here via {@see begin()}:
 *
 *   - Page title SHOWN  → the theme owns the `<h1>`. The builder owns none: the
 *     first heading that intends to be the primary (`<h1>`) renders as `<h2>`,
 *     and any later would-be `<h1>` is likewise demoted.
 *   - Page title HIDDEN → the first primary-intent heading becomes the single
 *     `<h1>`; later would-be `<h1>`s demote to `<h2>`.
 *   - Headings the author set to `<h2>`..`<h6>` are never changed.
 *
 * The decision is the builder's own — it consumes the visibility fact through a
 * public seam but owns the heading rank. It is a TRANSIENT render fact: it never
 * mutates the stored payload and is reset at the start of every render pass
 * ({@see LayoutRenderer::render()}). Levels are whitelist-normalized, so a
 * malicious/custom `level` value can never emit an arbitrary tag.
 *
 * The class is deliberately renderer-driven (not template-specific): the hero
 * section view and the generic heading block view both defer their primary rank
 * to it, so there is one deterministic primary-heading authority rather than
 * per-template branching.
 */
final class HeadingRankResolver
{
    /** @var list<string> */
    private const LEVELS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    private const DEFAULT_LEVEL = 'h2';

    private bool $externalTitlePresent = false;

    private bool $primaryAssigned = false;

    /**
     * Begin a render pass: record whether the host theme already renders the
     * page-title `<h1>` above this layout, and clear the per-render primary claim.
     */
    public function begin(bool $externalTitlePresent): void
    {
        $this->externalTitlePresent = $externalTitlePresent;
        $this->primaryAssigned = false;
    }

    /**
     * Resolve the semantic tag for a heading that intends level `$intended`.
     *
     * `$intended` is whitelist-normalized; a null/invalid value falls back to the
     * heading default (`h2`). Only `h1`-intent headings participate in the
     * single-primary contract; `h2`..`h6` pass through unchanged.
     */
    public function resolve(?string $intended): string
    {
        $level = in_array($intended, self::LEVELS, true) ? $intended : self::DEFAULT_LEVEL;

        if ($level !== 'h1') {
            return $level;
        }

        if (! $this->primaryAssigned) {
            $this->primaryAssigned = true;

            return $this->externalTitlePresent ? 'h2' : 'h1';
        }

        // A second (or later) would-be `<h1>` — demote so the document keeps one.
        return 'h2';
    }

    public function externalTitlePresent(): bool
    {
        return $this->externalTitlePresent;
    }
}
