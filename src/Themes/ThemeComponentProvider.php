<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use Plugins\PageBuilder\Components\Contracts\ComponentProvider;

/**
 * The Page Builder-built adapter that lets an active theme's declarative components enter the ONE component
 * authority through the unchanged PB-XREG-1 {@see ComponentProvider} contract (PB-XREG-4).
 *
 * It can only be created from the theme's own files ({@see self::fromTheme()}); its definitions are never
 * supplied by a caller. The registry still re-derives the theme identity from Core before accepting it
 * ({@see ThemeOriginIdentity}), so a forged slug or an inactive theme is refused.
 */
final class ThemeComponentProvider implements ComponentProvider
{
    /** @param  list<ThemeComponentDefinition>  $definitions */
    private function __construct(
        private readonly string $slug,
        private readonly string $version,
        private readonly array $definitions,
    ) {}

    /**
     * Read and build every component a theme declares. Atomic: one invalid file rejects the whole theme batch.
     *
     * @throws ThemeComponentException
     */
    public static function fromTheme(
        string $slug,
        string $version,
        ThemeComponentManifestReader $reader,
        ThemeComponentFactory $factory,
    ): self {
        $definitions = [];
        foreach ($reader->read($slug) as $document) {
            $definitions[] = $factory->make($slug, $document);
        }

        return new self($slug, $version !== '' ? $version : '0', $definitions);
    }

    public function namespace(): string
    {
        return $this->slug;
    }

    public function version(): string
    {
        return $this->version;
    }

    /** @return list<ThemeComponentDefinition> */
    public function components(): iterable
    {
        return $this->definitions;
    }
}
