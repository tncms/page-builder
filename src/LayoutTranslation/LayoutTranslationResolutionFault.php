<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

/**
 * Why a layout translation relationship could not be resolved — LT-1 (locked).
 *
 * A typed replacement for stringly-typed reasons across the resolver → validator
 * seam. It is a relationship-validity concern (the request is not a translatable
 * relationship), distinct from the business status dimensions. `null` on a
 * descriptor means the relationship is well-formed.
 */
enum LayoutTranslationResolutionFault: string
{
    /** Owner type is not an editable Page Builder owner (page/post). */
    case UnsupportedOwnerType = 'unsupported_owner_type';

    /** Locale is not an enabled public locale. */
    case LocaleNotEnabled = 'locale_not_enabled';
}
