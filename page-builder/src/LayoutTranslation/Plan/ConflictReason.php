<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Plan;

/**
 * The canonical reason a plan item is a conflict or requires review (LT-6, per
 * LT-0-ADR-001 D9).
 *
 * Layer: Translation (plan). `MissingCorrespondence` is reserved for
 * ambiguous/absent correspondence a later phase surfaces.
 */
enum ConflictReason: string
{
    /** Source text changed after the target text was translated. */
    case TranslatedContentDiverged = 'translated_content_diverged';

    /** Source deleted a node the target modified. */
    case SourceRemovedTargetModified = 'source_removed_target_modified';

    /** Source and target changed the same node's structure incompatibly. */
    case StructuralConflict = 'structural_conflict';

    /** Reserved: correspondence is missing or ambiguous. */
    case MissingCorrespondence = 'missing_correspondence';
}
