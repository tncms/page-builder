<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Commands;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;

/**
 * Immutable clone request (LT-3).
 *
 * Layer: Translation (clone command). Carries canonical LT-1 identities only —
 * never loose arrays, and never locale as the primary input. It is a pure data
 * carrier; identity coherence (same group, distinct translations, canonical
 * inputs) is validated by the eligibility evaluator so those become typed
 * outcomes rather than construction exceptions.
 */
final class CloneLayoutTranslationCommand
{
    public function __construct(
        public readonly TranslationGroupKey $group,
        public readonly PageTranslationKey $source,
        public readonly PageTranslationKey $target,
        public readonly ?string $actor = null,
    ) {}

    public function isSameTranslation(): bool
    {
        return $this->source->equals($this->target);
    }

    public function isSameGroup(): bool
    {
        return $this->source->group()->equals($this->target->group());
    }

    public function isCanonical(): bool
    {
        return $this->source->group()->equals($this->group)
            && $this->target->group()->equals($this->group);
    }
}
