<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\State;

/**
 * The synchronization dimension of a layout translation — LT-1 (locked).
 *
 * Describes a translation's alignment with its source baseline. Independent of
 * existence and lifecycle. It is `NotApplicable` for the source locale, and for
 * any translation with no layout. LT-1 only ever produces `NotApplicable`; the
 * remaining states are produced by the synchronization phases (LT-5+).
 *
 * Canonical transitions (rules only; no synchronization is implemented in LT-1):
 *   NotApplicable → InSync (first baseline recorded);
 *   InSync → SourceChanged (source edited) | TargetChanged (target edited);
 *   SourceChanged/TargetChanged → Diverged (both edited) | InSync (re-synced);
 *   any → Conflicted (irreconcilable) → InSync (resolved).
 */
enum LayoutSynchronizationState: string
{
    case NotApplicable = 'not_applicable';

    case InSync = 'in_sync';

    case SourceChanged = 'source_changed';

    case TargetChanged = 'target_changed';

    case Diverged = 'diverged';

    case Conflicted = 'conflicted';
}
