<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Explainability;

/**
 * The fidelity of an explainability report (LT-9B, per LT-9A §6, INV-LT-9-TL-04).
 *
 * Layer: Translation (timeline / explainability). `Full` — snapshots were available, so the classification
 * and conflict reason were re-derived via the frozen LT-5/LT-6. `MetadataOnly` — snapshots were not
 * retained, so the report degrades to the origins, change types, and decisions recorded in history alone.
 */
enum ExplanationMode: string
{
    case Full = 'full';

    case MetadataOnly = 'metadata_only';
}
