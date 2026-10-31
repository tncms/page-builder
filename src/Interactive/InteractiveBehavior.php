<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Interactive;

/**
 * The client-side behavior an interactive component hydrates with (PB-XREG-3, Interactive Runtime 1.0.0).
 *
 * `id` is a namespaced `{provider}:{local}` identifier owned by the provider (validated against the server-derived
 * plugin slug). The provider's module calls `window.TNPageBuilderInteractive.define(id, {mount})`; the runtime
 * mounts every rendered root carrying this behavior. `version` participates in cache busting. `contexts` limits
 * where the behavior hydrates: `public` (anonymous frontend) and/or `editor` (Live Builder canvas).
 */
final class InteractiveBehavior
{
    public const CONTEXT_PUBLIC = 'public';

    public const CONTEXT_EDITOR = 'editor';

    public const CONTEXTS = [self::CONTEXT_PUBLIC, self::CONTEXT_EDITOR];

    public const MAX_ASSETS = 8;

    /**
     * @param  array<int, InteractiveAsset>  $assets
     * @param  array<int, string>  $contexts
     */
    public function __construct(
        private readonly string $id,
        private readonly string $version,
        private readonly array $assets,
        private readonly array $contexts = self::CONTEXTS,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function version(): string
    {
        return $this->version;
    }

    /** @return array<int, InteractiveAsset> declaration order */
    public function assets(): array
    {
        return array_values($this->assets);
    }

    /** @return array<int, string> */
    public function contexts(): array
    {
        return array_values($this->contexts);
    }

    public function supports(string $context): bool
    {
        return in_array($context, $this->contexts, true);
    }

    /**
     * Assets in deterministic dependency order: a depth-first post-order walk in declaration order, so every
     * asset follows its dependencies and ties keep declaration order. Assumes a validated (acyclic) graph; a
     * cycle can never loop (visited set) — the validator rejects it before registration.
     *
     * @return array<int, InteractiveAsset>
     */
    public function orderedAssets(): array
    {
        $byHandle = [];
        foreach ($this->assets() as $asset) {
            $byHandle[$asset->handle()] ??= $asset;
        }

        $ordered = [];
        $visited = [];
        $visit = function (InteractiveAsset $asset) use (&$visit, &$ordered, &$visited, $byHandle): void {
            if (isset($visited[$asset->handle()])) {
                return;
            }
            $visited[$asset->handle()] = true;
            foreach ($asset->deps() as $dep) {
                if (isset($byHandle[$dep])) {
                    $visit($byHandle[$dep]);
                }
            }
            $ordered[] = $asset;
        };

        foreach ($byHandle as $asset) {
            $visit($asset);
        }

        return $ordered;
    }

    /**
     * Full structural identity (id, version, contexts, de-duplicated assets in dependency order) used for
     * idempotency and conflict checks. Internal — never exposed in snapshots, manifests or diagnostics.
     */
    public function signature(): string
    {
        return (string) json_encode([
            trim($this->id),
            trim($this->version),
            $this->contexts(),
            array_map(fn (InteractiveAsset $a): array => $a->toArray(), $this->orderedAssets()),
        ]);
    }

    /** A bounded, path-free fingerprint used by registry snapshots: `{id}@{version}`. */
    public function fingerprint(): string
    {
        return trim($this->id).'@'.trim($this->version);
    }
}
