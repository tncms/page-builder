<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Interactive;

use Plugins\PageBuilder\Components\ComponentIdentifier;
use Plugins\PageBuilder\Interactive\Contracts\InteractiveComponentDefinition;
use Throwable;

/**
 * Structural certification of an {@see InteractiveBehavior} (PB-XREG-3). Returns a list of bounded error strings
 * (empty = compliant). Never echoes provider exception messages, never touches the filesystem — file existence and
 * containment are checked at render time by {@see InteractiveAssetResolver}, against the published namespace.
 */
final class InteractiveBehaviorValidator
{
    /** Local asset handle: lowercase, starts alphanumeric, ≤ 48 chars. */
    public const HANDLE_PATTERN = '/^[a-z0-9][a-z0-9-]{0,47}$/';

    /** Relative path: lowercase segments separated by "/", final segment with a supported extension. */
    public const PATH_PATTERN = '/^[a-z0-9][a-z0-9_-]*(\/[a-z0-9][a-z0-9_.-]*)*\.(css|js|mjs)$/';

    public const MAX_PATH_LENGTH = 200;

    public const MAX_VERSION_LENGTH = 32;

    /** @return array<int, string> */
    public function errorsFor(InteractiveComponentDefinition $definition, string $namespace): array
    {
        try {
            $behavior = $definition->behavior();
        } catch (Throwable) {
            return ['behavior() threw.'];
        }

        return $this->errors($behavior, $namespace);
    }

    /** @return array<int, string> */
    public function errors(InteractiveBehavior $behavior, string $namespace): array
    {
        $errors = [];
        $id = $behavior->id();

        if (ComponentIdentifier::classify($id) !== ComponentIdentifier::KIND_EXTERNAL
            || ! str_starts_with($id, $namespace.':')) {
            $errors[] = 'behavior id must be a "'.$namespace.':<local>" identifier.';
        }

        $version = trim($behavior->version());
        if ($version === '' || strlen($version) > self::MAX_VERSION_LENGTH || preg_match('/^[0-9A-Za-z.+-]+$/', $version) !== 1) {
            $errors[] = 'behavior version must be a short non-empty version string.';
        }

        $contexts = $behavior->contexts();
        if ($contexts === [] || array_diff($contexts, InteractiveBehavior::CONTEXTS) !== []) {
            $errors[] = 'behavior contexts must be a non-empty subset of public, editor.';
        }

        $assets = $behavior->assets();
        foreach ($assets as $asset) {
            if (! $asset instanceof InteractiveAsset) {
                return [...$errors, 'every asset must be an InteractiveAsset.'];
            }
        }

        // A byte-identical repeated declaration collapses to one asset; a handle declared twice with a different
        // kind, path or deps is a conflict. Bounds apply to the de-duplicated set.
        $handles = [];
        $unique = [];
        foreach ($assets as $asset) {
            $handle = $asset->handle();
            if (isset($handles[$handle])) {
                if ($handles[$handle]->toArray() !== $asset->toArray()) {
                    $errors[] = 'asset handle "'.$this->bounded($handle).'" is declared twice with different values.';
                }

                continue;
            }
            $handles[$handle] = $asset;
            $unique[] = $asset;
        }

        if ($unique === [] || count($unique) > InteractiveBehavior::MAX_ASSETS) {
            $errors[] = 'behavior must declare 1..'.InteractiveBehavior::MAX_ASSETS.' assets.';

            return $errors;
        }

        foreach ($unique as $asset) {
            $handle = $asset->handle();
            if (preg_match(self::HANDLE_PATTERN, $handle) !== 1) {
                $errors[] = 'asset handle "'.$this->bounded($handle).'" is invalid.';
            }

            if (! $this->safePath($asset)) {
                $errors[] = 'asset "'.$this->bounded($handle).'" path must be a relative '.$asset->kind().' file inside the provider asset namespace.';
            }
        }

        foreach ($unique as $asset) {
            foreach ($asset->deps() as $dep) {
                if (! is_string($dep) || ! isset($handles[$dep])) {
                    $errors[] = 'asset "'.$this->bounded($asset->handle()).'" depends on an undeclared handle.';
                }
            }
        }

        if ($errors === [] && $this->hasCycle($handles)) {
            $errors[] = 'asset dependencies contain a cycle.';
        }

        return $errors;
    }

    private function safePath(InteractiveAsset $asset): bool
    {
        $path = $asset->path();

        if ($path === '' || strlen($path) > self::MAX_PATH_LENGTH || preg_match(self::PATH_PATTERN, $path) !== 1) {
            return false;
        }

        // The grammar already excludes ".", "%", ":", "\" and NUL in the leading position and any "..",
        // but reject traversal segments explicitly so the intent is unambiguous.
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, '..')) {
                return false;
            }
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $asset->kind() === InteractiveAsset::KIND_CSS
            ? $extension === 'css'
            : in_array($extension, ['js', 'mjs'], true);
    }

    /** @param  array<string, InteractiveAsset>  $handles */
    private function hasCycle(array $handles): bool
    {
        $state = []; // handle => 1 visiting, 2 done
        $visit = function (string $handle) use (&$visit, &$state, $handles): bool {
            if (($state[$handle] ?? 0) === 1) {
                return true;
            }
            if (($state[$handle] ?? 0) === 2) {
                return false;
            }
            $state[$handle] = 1;
            foreach ($handles[$handle]->deps() as $dep) {
                if ($visit($dep)) {
                    return true;
                }
            }
            $state[$handle] = 2;

            return false;
        };

        foreach (array_keys($handles) as $handle) {
            if ($visit((string) $handle)) {
                return true;
            }
        }

        return false;
    }

    private function bounded(string $value): string
    {
        return mb_substr(preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '', 0, 48);
    }
}
