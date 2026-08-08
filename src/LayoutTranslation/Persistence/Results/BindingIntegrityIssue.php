<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Results;

/**
 * Integrity issues a persisted binding may exhibit against the outside world —
 * LT-2.
 *
 * Layer: Persistence / Result. These require context BEYOND the value objects
 * (owner existence, referenced-layout existence, persisted-state readability) —
 * they are not structural VO invariants (which cannot be violated).
 */
enum BindingIntegrityIssue: string
{
    /** The binding's owning content no longer exists. */
    case OrphanedBinding = 'orphaned_binding';

    /** The referenced pb_layouts document no longer exists. */
    case MissingReferencedLayout = 'missing_referenced_layout';

    /** The binding's owner type is not an editable Page Builder owner. */
    case UnsupportedOwner = 'unsupported_owner';

    /** The persisted state could not be read as a valid status (corrupt data). */
    case UnreadablePersistedState = 'unreadable_persisted_state';

    /** The stored canonical identity keys do not cohere. */
    case CanonicalKeyMismatch = 'canonical_key_mismatch';
}
