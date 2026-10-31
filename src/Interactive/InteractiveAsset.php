<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Interactive;

/**
 * One provider-owned file an interactive behavior needs (PB-XREG-3, Interactive Runtime contract 1.0.0).
 *
 * `path` is RELATIVE to the provider plugin's published asset namespace (`public/vendor/{slug}/…`, populated by
 * Core's PluginAssetPublisher from the plugin's `assets.source`). It is never a URL and never persisted in a
 * layout: the URL is derived server-side at render time from the active, identity-verified registration.
 * `deps` name other handles of the SAME behavior. Grammar and bounds are enforced by
 * {@see InteractiveBehaviorValidator} at registration.
 */
final class InteractiveAsset
{
    public const KIND_CSS = 'css';

    public const KIND_MODULE = 'module';

    /**
     * @param  array<int, string>  $deps
     */
    private function __construct(
        private readonly string $kind,
        private readonly string $handle,
        private readonly string $path,
        private readonly array $deps,
    ) {}

    /** @param  array<int, string>  $deps */
    public static function css(string $handle, string $path, array $deps = []): self
    {
        return new self(self::KIND_CSS, $handle, $path, array_values($deps));
    }

    /** An ES module (`<script type="module">`), the only supported JavaScript kind. @param  array<int, string>  $deps */
    public static function module(string $handle, string $path, array $deps = []): self
    {
        return new self(self::KIND_MODULE, $handle, $path, array_values($deps));
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function handle(): string
    {
        return $this->handle;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return array<int, string> */
    public function deps(): array
    {
        return $this->deps;
    }

    /** @return array{kind: string, handle: string, path: string, deps: array<int, string>} */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'handle' => $this->handle, 'path' => $this->path, 'deps' => $this->deps];
    }
}
