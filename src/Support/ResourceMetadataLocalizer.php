<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Repository\DesignResource;

/**
 * Resolves a Design Resource's human presentation metadata (name, description) for a single
 * Admin UI locale (PB-TEMPLATE-REGISTRY-1-H3).
 *
 * Pure and deterministic: it reads only the resource's optional `localized` maps plus the
 * requested/fallback locale, and never touches identity or the payload. The fallback chain is
 * the canonical one — requested Admin UI locale -> platform default locale -> the resource's
 * invariant (legacy single-string) field — so a missing translation degrades safely and legacy
 * single-string metadata stays backward compatible.
 *
 * It owns no locale authority: the caller passes the resolved Admin UI locale + platform default
 * (read from Core's read-only PublicLocaleContext contract via {@see CatalogLocale}). Machine
 * fields (rid, source, kind, resourceId, version, category, tags) are locale-invariant by
 * contract and are never resolved here.
 */
final class ResourceMetadataLocalizer
{
    public function name(DesignResource $resource, ?string $locale, ?string $fallback = null): string
    {
        return $this->resolve($resource->localized['name'] ?? null, $locale, $fallback) ?? $resource->name;
    }

    public function description(DesignResource $resource, ?string $locale, ?string $fallback = null): ?string
    {
        return $this->resolve($resource->localized['description'] ?? null, $locale, $fallback) ?? $resource->description;
    }

    /**
     * Pick the first non-empty string for the requested locale, then the fallback locale.
     * Returns null when neither is present so the caller degrades to the invariant field.
     *
     * @param  mixed  $map  the resource's per-field locale map (lowercased keys) or anything else
     */
    private function resolve(mixed $map, ?string $locale, ?string $fallback): ?string
    {
        if (! is_array($map)) {
            return null;
        }

        foreach ([$locale, $fallback] as $code) {
            if (! is_string($code) || $code === '') {
                continue;
            }
            $value = $map[strtolower($code)] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
