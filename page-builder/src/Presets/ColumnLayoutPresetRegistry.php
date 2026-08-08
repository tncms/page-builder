<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Presets;

use Plugins\PageBuilder\Presets\Contracts\ColumnLayoutPresetProvider;

/**
 * Collects column-layout preset providers and produces the merged catalog the
 * Layout popup renders (Phase 26.1.1).
 *
 * Mirrors the Pattern Library discovery model: providers are merged in ascending
 * priority so higher-priority providers override lower ones on a duplicate preset
 * id (last write wins), the result is de-duplicated by id, and presets are sorted
 * by their `order` field for a stable, metadata-driven layout.
 */
final class ColumnLayoutPresetRegistry
{
    /** Canonical category order for the popup's tabs; unknown categories append after. */
    private const CATEGORY_ORDER = ['common', 'equal', 'sidebar', 'hero', 'landing', 'magazine', 'commerce'];

    /** Human labels for the known categories. */
    private const CATEGORY_LABELS = [
        'common' => 'Common',
        'equal' => 'Equal Columns',
        'sidebar' => 'Sidebar',
        'hero' => 'Hero',
        'landing' => 'Landing',
        'magazine' => 'Magazine',
        'commerce' => 'Commerce',
    ];

    /** @var array<int, ColumnLayoutPresetProvider> */
    private array $providers = [];

    public function register(ColumnLayoutPresetProvider $provider): self
    {
        $this->providers[] = $provider;

        return $this;
    }

    /**
     * The merged, de-duplicated, order-sorted preset catalog.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $sorted = $this->providers;
        usort($sorted, static fn ($a, $b) => $a->priority() <=> $b->priority());

        $byId = [];
        foreach ($sorted as $provider) {
            foreach ($provider->presets() as $preset) {
                $id = $preset['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    $byId[$id] = $preset; // higher priority runs later → overrides.
                }
            }
        }

        $presets = array_values($byId);
        usort($presets, static fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return $presets;
    }

    /**
     * The categories actually present in the catalog, in canonical order, each as
     * {id, label}. The popup adds its own dynamic All / Favorites / Recent tabs.
     *
     * @return array<int, array{id: string, label: string}>
     */
    public function categories(): array
    {
        $present = [];
        foreach ($this->all() as $preset) {
            $category = $preset['category'] ?? null;
            if (is_string($category) && $category !== '') {
                $present[$category] = true;
            }
        }

        $ordered = [];
        foreach (self::CATEGORY_ORDER as $id) {
            if (isset($present[$id])) {
                $ordered[] = ['id' => $id, 'label' => self::CATEGORY_LABELS[$id] ?? ucfirst($id)];
                unset($present[$id]);
            }
        }

        // Any provider-defined categories outside the canonical list, appended.
        foreach (array_keys($present) as $id) {
            $ordered[] = ['id' => $id, 'label' => self::CATEGORY_LABELS[$id] ?? ucfirst($id)];
        }

        return $ordered;
    }
}
