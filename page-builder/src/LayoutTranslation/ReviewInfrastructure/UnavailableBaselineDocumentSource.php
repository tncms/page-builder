<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;

/**
 * The fail-closed default baseline document source (LT-8D).
 *
 * Layer: Translation (review infrastructure). Returns null for every target: the baseline PAYLOAD is not
 * persisted yet (LT-4 stores a fingerprint only), and reconstructing it would require a persistence change
 * that is out of scope for LT-8D wiring. With this default bound, the review provider fails closed and the
 * workspace shows a fail-soft "unavailable" state rather than fabricating a diff. A later persistence phase
 * binds a real source in its place — no runtime, presentation, or contract change required here.
 */
final class UnavailableBaselineDocumentSource implements BaselineDocumentSourceInterface
{
    public function documentFor(PageTranslationKey $target): ?array
    {
        return null;
    }
}
