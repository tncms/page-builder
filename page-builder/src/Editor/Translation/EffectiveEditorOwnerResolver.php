<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\TargetOwnerResolverInterface;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationResolver;

/**
 * Server-authoritative resolution of the effective editor layout owner (LT-M1B).
 *
 * Given the canonical page + a requested locale, it consumes the frozen
 * {@see LayoutTranslationResolver} (locale validity, group identity, source
 * detection, feature-flag dormancy) and maps the result to the physical
 * `pb_layouts` owner:
 *
 *   - source locale → the canonical owner (`page`, page.id) — legacy-compatible;
 *   - non-default    → the Core-derived (`content_translation`, ContentTranslation.id).
 *
 * The target owner is resolved directly from Core's ContentTranslation identity
 * via the frozen {@see TargetOwnerResolverInterface}; it does NOT require an
 * existing LT binding (the binding is created by the clone transaction). Pure
 * resolution — no cloning, no writes. Invalid/forged contexts fail closed.
 */
final class EffectiveEditorOwnerResolver
{
    public function __construct(
        private readonly LayoutTranslationResolver $resolver,
        private readonly TargetOwnerResolverInterface $targetOwners,
    ) {}

    /**
     * Read-only owner resolution for public/preview render paths (LT-M1B Inc2).
     *
     * Returns the effective `(ownerType, ownerId)` for the canonical page + locale
     * using the SAME resolution as the editor, but never throws and never writes:
     * on any resolution fault (disabled locale, no content translation, dormant
     * feature flag) it falls back to the canonical owner. Rendering must never
     * provision or clone, so this deliberately does NOT touch the provisioner.
     *
     * @return array{0: string, 1: int|string}
     */
    public function resolveForReadOrCanonical(string $canonicalType, int|string $canonicalId, ?string $requestedLocale): array
    {
        try {
            $effective = $this->resolve($canonicalType, $canonicalId, $requestedLocale);

            return [$effective->ownerType, $effective->ownerId];
        } catch (EditorOwnerResolutionException) {
            return [$canonicalType, $canonicalId];
        }
    }

    public function resolve(string $canonicalType, int|string $canonicalId, ?string $requestedLocale): EffectiveLayoutOwner
    {
        $descriptor = $this->resolver->resolve($canonicalType, $canonicalId, $requestedLocale);

        if (! $descriptor->isResolvable()) {
            throw EditorOwnerResolutionException::fromFault($descriptor->fault(), $requestedLocale);
        }

        $group = $descriptor->group();
        $translation = $descriptor->translation();

        // Source (default) locale: the canonical layout IS the source layout.
        if ($descriptor->isSourceLocale()) {
            return new EffectiveLayoutOwner(
                $canonicalType,
                $canonicalId,
                $canonicalType,
                $canonicalId,
                $requestedLocale,
                $descriptor->locale(),
                $descriptor->sourceLocale,
                true,
                null,
                $translation,
                $group,
            );
        }

        // Non-default locale: derive the target owner from Core's ContentTranslation
        // identity. No binding is required — this is the first-open target discovery.
        $owner = $this->targetOwners->resolve($translation);
        if ($owner === null) {
            throw EditorOwnerResolutionException::translationNotFound($descriptor->locale());
        }

        return new EffectiveLayoutOwner(
            $canonicalType,
            $canonicalId,
            $owner->ownerType(),
            $owner->ownerId(),
            $requestedLocale,
            $descriptor->locale(),
            $descriptor->sourceLocale,
            false,
            (int) $owner->ownerId(),
            $translation,
            $group,
        );
    }
}
