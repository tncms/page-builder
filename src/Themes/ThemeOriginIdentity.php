<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use Plugins\PageBuilder\Components\ComponentIdentifier;
use TheNguyen\CMS\Services\ExtensionManager;
use TheNguyen\CMS\Services\ThemeManager;
use Throwable;

/**
 * Server-side theme identity for Page Builder (PB-XREG-4), derived ONLY from the Core theme authority.
 *
 * The eligible origins are Core's effective active theme ({@see ThemeManager::active()} — the same theme the
 * `theme::` view namespace renders) followed by its declared parent chain ({@see ThemeManager::resolveParentChain()}).
 * An installed-but-inactive theme is never eligible. A chain Core reports as broken yields no eligible theme.
 *
 * A theme slug is a component namespace only when it is a valid identifier segment, is not Page Builder's
 * reserved namespace, and is not the slug of ANY discovered plugin (active or not) — so activating a plugin can
 * never change who owns an already persisted `{slug}:{name}` id.
 */
final class ThemeOriginIdentity
{
    public const INACTIVE = 'theme.inactive';

    public const SLUG_INVALID = 'theme.slug_invalid';

    public const NAMESPACE_RESERVED = 'theme.namespace_reserved';

    public const NAMESPACE_CONFLICT = 'theme.namespace_conflict';

    public function __construct(
        private readonly ?ThemeManager $themes = null,
        private readonly ?ExtensionManager $extensions = null,
    ) {}

    /**
     * The active theme chain, child first. [] when Core has no effective theme or reports a broken chain.
     *
     * @return list<string>
     */
    public function activeChain(): array
    {
        try {
            $themes = $this->themes();
            $active = $themes->active();

            if ($active === null) {
                return [];
            }

            $resolved = $themes->resolveParentChain($active->slug);

            return $resolved['errors'] === [] ? array_values(array_map('strval', $resolved['chain'])) : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** Null when `$slug` may register as a theme component namespace now, else a bounded diagnostic code. */
    public function ineligibility(string $slug): ?string
    {
        if (strlen($slug) > ComponentIdentifier::MAX_SEGMENT_LENGTH
            || preg_match(ComponentIdentifier::SEGMENT_PATTERN, $slug) !== 1) {
            return self::SLUG_INVALID;
        }

        if ($slug === ComponentIdentifier::RESERVED_NAMESPACE) {
            return self::NAMESPACE_RESERVED;
        }

        if (! in_array($slug, $this->activeChain(), true)) {
            return self::INACTIVE;
        }

        if (in_array($slug, $this->pluginSlugs(), true)) {
            return self::NAMESPACE_CONFLICT;
        }

        return null;
    }

    public function isEligible(string $slug): bool
    {
        return $this->ineligibility($slug) === null;
    }

    /** @return list<string> */
    private function pluginSlugs(): array
    {
        try {
            return array_values(array_map(static fn ($plugin): string => (string) $plugin->slug, $this->extensions()->plugins()));
        } catch (Throwable) {
            // Without a trustworthy plugin list, no theme namespace can be proven collision-free.
            return [ComponentIdentifier::RESERVED_NAMESPACE, ...$this->activeChain()];
        }
    }

    private function themes(): ThemeManager
    {
        return $this->themes ?? app('cms.theme');
    }

    private function extensions(): ExtensionManager
    {
        return $this->extensions ?? app(ExtensionManager::class);
    }
}
