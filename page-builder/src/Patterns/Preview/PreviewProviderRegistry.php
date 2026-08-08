<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Preview;

/**
 * Registry of {@see PreviewProvider}s keyed by preview type (Phase 25.1, §13). The
 * {@see PatternPreviewResolver} asks it for the provider matching a manifest's
 * declared `preview.type`; an unknown type falls back to the default image provider
 * so a preview is always resolvable.
 */
final class PreviewProviderRegistry
{
    /** @var array<string, PreviewProvider> */
    private array $providers = [];

    private readonly PreviewProvider $default;

    public function __construct(?PreviewProvider $default = null)
    {
        $this->default = $default ?? new ImagePreviewProvider();
        $this->register($this->default);
    }

    public function register(PreviewProvider $provider): self
    {
        $this->providers[$provider->type()] = $provider;

        return $this;
    }

    public function forType(string $type): PreviewProvider
    {
        return $this->providers[$type] ?? $this->default;
    }

    public function has(string $type): bool
    {
        return isset($this->providers[$type]);
    }

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_keys($this->providers);
    }
}
