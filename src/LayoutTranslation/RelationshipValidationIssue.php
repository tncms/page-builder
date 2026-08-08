<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

/**
 * The fail-closed integrity issues a resolved descriptor may exhibit (LT-1,
 * locked).
 *
 * The validator only reports concerns that require context BEYOND the value
 * objects themselves — relationship validity (from the resolver) and integrity
 * against the outside world (owner existence, storage soundness). Purely
 * structural invariants (identity coherence, cross-dimension state consistency,
 * capability vs status) are NOT here: they are enforced at value-object
 * construction and can never be violated, and capability-vs-status is an
 * Eligibility concern, not validation.
 */
enum RelationshipValidationIssue: string
{
    /** Owner type is not an editable Page Builder owner (page/post). */
    case UnsupportedOwnerType = 'unsupported_owner_type';

    /** Locale is not an enabled public locale — invalid locale ownership. */
    case LocaleNotEnabled = 'locale_not_enabled';

    /** The layout reference is broken or ambiguous (duplicate/dangling storage). */
    case InvalidLayoutReference = 'invalid_layout_reference';

    /** A layout exists but its owning content does not — a broken/dangling reference. */
    case OrphanLayout = 'orphan_layout';
}
