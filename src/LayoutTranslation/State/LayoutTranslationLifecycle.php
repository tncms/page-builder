<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\State;

/**
 * The publication lifecycle dimension of a layout translation — LT-1 (locked).
 *
 * Applies only when a layout exists ({@see LayoutTranslationExistence::Exists}).
 * Independent of existence and synchronization.
 *
 * Canonical transitions (rules only; no transition is executed in LT-1):
 *   Draft → Published → Archived, and Archived → Draft (restore). Published may
 *   return to Draft (unpublish). A transition never crosses into another
 *   dimension.
 */
enum LayoutTranslationLifecycle: string
{
    case Draft = 'draft';

    case Published = 'published';

    case Archived = 'archived';
}
