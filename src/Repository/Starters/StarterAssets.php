<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Starters;

/**
 * Canonical, Page Builder-owned public asset URLs for the free starter/pattern
 * library (PB-FREE-LIBRARY-DESIGN-1-B).
 *
 * The referenced files ship inside the plugin's own public namespace
 * (`plugins/page-builder/public/img/placeholders/**`) and are served under the
 * Core-owned plugin asset namespace (`asset('vendor/page-builder/…')`) via the
 * `assets` manifest declaration. They are deliberately neutral, self-authored
 * SVG placeholders (no copyrighted media, no remote host), so a curated resource
 * always renders a valid, safe media surface that users replace after import —
 * never a broken `/img/starters/**` 404.
 */
final class StarterAssets
{
    /** Neutral 16:9 image placeholder (heroes, screenshots, gallery slots). */
    public const IMAGE_PLACEHOLDER = '/vendor/page-builder/img/placeholders/image.svg';

    /** Neutral logo placeholder (logo strips). */
    public const LOGO_PLACEHOLDER = '/vendor/page-builder/img/placeholders/logo.svg';

    /**
     * Art-directed, original, self-authored vector media (PB-DEMO-DESIGN-R1-A).
     * A cohesive premium set (ink / cobalt / cream + ember) shipped in the plugin's
     * own public namespace for image-led curated resources. Pure vector, no script,
     * no remote reference, deterministic — a real art surface users replace after
     * import. Served, like the placeholders, under `asset('vendor/page-builder/…')`.
     */
    public const MEDIA_DIR = '/vendor/page-builder/img/media';

    /** Three distinct 16:9 case-study / project tiles. */
    public const MEDIA_CASE_01 = self::MEDIA_DIR.'/case-01.svg';

    public const MEDIA_CASE_02 = self::MEDIA_DIR.'/case-02.svg';

    public const MEDIA_CASE_03 = self::MEDIA_DIR.'/case-03.svg';

    /** Four credible wordmark-style partner marks (single-tone, grayscale-safe). */
    public const MEDIA_MARK_01 = self::MEDIA_DIR.'/mark-01.svg';

    public const MEDIA_MARK_02 = self::MEDIA_DIR.'/mark-02.svg';

    public const MEDIA_MARK_03 = self::MEDIA_DIR.'/mark-03.svg';

    public const MEDIA_MARK_04 = self::MEDIA_DIR.'/mark-04.svg';

    /** Editorial abstract hero artwork (split-hero media column). */
    public const MEDIA_HERO_STUDIO = self::MEDIA_DIR.'/hero-studio.svg';

    /** Public directory (served) that holds the real, generated catalog thumbnails. */
    public const THUMBNAIL_DIR = '/vendor/page-builder/img/thumbnails';

    /**
     * The deterministic, collision-safe catalog thumbnail URL for a starter resource
     * (PB-FREE-LIBRARY-DESIGN-1-F). The file is a real render of the resource's canonical
     * payload, generated offline and shipped inside the plugin's public namespace
     * (`plugins/page-builder/public/img/thumbnails/{kind}-{id}.webp`). RID identity is
     * `{kind}:{id}`, so `{kind}-{id}` is unique across the catalog. The shipped file works
     * with no Node, network, or write permission at runtime.
     */
    public static function thumbnailUrl(string $kind, string $id): string
    {
        return self::THUMBNAIL_DIR.'/'.$kind.'-'.$id.'.webp';
    }
}
