<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;

/**
 * Supplies the baseline (last-synced source) DOCUMENT for a target (LT-8D).
 *
 * Layer: Translation (review infrastructure). The three-way diff (LT-5) needs the baseline document B,
 * but LT-4 persists only its content-addressed fingerprint (Decision B2) — no payload. This seam is the
 * single, explicit place a baseline document is obtained. Its production backing (persisting the baseline
 * snapshot) is a later, separately-governed persistence phase; until then the default returns null and the
 * workspace degrades to a fail-soft unavailable state. A returned document MUST content-address to the
 * stored baseline fingerprint (the provider verifies this) so the plan stays provenance-consistent.
 */
interface BaselineDocumentSourceInterface
{
    /**
     * @return array<string, mixed>|null  the baseline layout document, or null when unavailable
     */
    public function documentFor(PageTranslationKey $target): ?array;
}
