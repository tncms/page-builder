<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Library;

use Plugins\PageBuilder\Library\Availability\ResourceAvailabilityResolver;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;
use Plugins\PageBuilder\Support\ResourceMetadataLocalizer;

/**
 * Metadata-first catalog feed for the unified Resource Browser (PB-TEMPLATE-REGISTRY-2-B).
 *
 * A thin, read-only projection over the ONE certified source-agnostic authority
 * ({@see DesignResourceRepositoryInterface}), reusing the H3 localization
 * ({@see ResourceMetadataLocalizer}) and the canonical availability verdict
 * ({@see ResourceAvailabilityResolver}). It owns no discovery, no payload, no
 * persistence, and no rendering: cards are metadata only, so a browsing list never
 * ships a resource's layout payload (that stays lazy for R2-C preview/insert).
 *
 * Kind mapping mirrors Registry 1: section resources are "patterns" (insertable),
 * page resources are "templates" (whole-page). Block and kit kinds are intentionally
 * excluded from the browser. Human labels resolve for the Admin UI locale (never the
 * editing or frontend locale); every other field is locale-invariant machine data.
 */
final class ResourceCatalogService
{
    public function __construct(
        private readonly DesignResourceRepositoryInterface $repository,
        private readonly ResourceAvailabilityResolver $availability,
        private readonly ResourceMetadataLocalizer $localizer,
    ) {
    }

    /**
     * The unified browser catalog, split by browser kind and deterministically ordered
     * by resolved (Admin UI locale) name. $locale/$fallback come from the verified Core
     * Admin-UI-locale authority via CatalogLocale; null degrades to invariant metadata.
     *
     * @return array{patterns: array<int, array<string, mixed>>, templates: array<int, array<string, mixed>>}
     */
    public function catalog(?string $locale = null, ?string $fallback = null): array
    {
        $patterns = [];
        $templates = [];

        foreach ($this->repository->all() as $resource) {
            // Raw component substrate (registry-generated palette fallback) is not a
            // curated browse item: it stays registered/materializable by RID but never
            // masquerades as a curated resource in the unified browser (§6.1).
            if (! $resource->browsable) {
                continue;
            }

            if ($resource->kind === Rid::KIND_SECTION) {
                $patterns[] = $this->item($resource, 'pattern', $locale, $fallback);
            } elseif ($resource->kind === Rid::KIND_PAGE) {
                $templates[] = $this->item($resource, 'template', $locale, $fallback);
            }
            // block / kit kinds are not browser resources (Registry 1 rules 4/5).
        }

        $byName = static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']);
        usort($patterns, $byName);
        usort($templates, $byName);

        return ['patterns' => $patterns, 'templates' => $templates];
    }

    /**
     * One metadata-only card. Machine identity (rid/source/category/tags) stays
     * invariant; name/description resolve by Admin UI locale; availability is the
     * canonical verdict (never recomputed on the client). No payload is included.
     *
     * @return array<string, mixed>
     */
    private function item(DesignResource $resource, string $browserKind, ?string $locale, ?string $fallback): array
    {
        $availability = $this->availability->resolve($resource);

        return [
            'rid' => $resource->rid,
            'kind' => $browserKind,
            'source' => $resource->source,
            'category' => $resource->category,
            'tags' => $resource->tags,
            'name' => $this->localizer->name($resource, $locale, $fallback),
            'description' => $this->localizer->description($resource, $locale, $fallback),
            'preview' => $resource->preview,
            'available' => $availability->available,
            'locked' => $availability->locked,
            'requiredCapability' => $availability->requiredCapability,
        ];
    }
}
