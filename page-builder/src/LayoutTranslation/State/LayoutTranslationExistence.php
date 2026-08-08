<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\State;

/**
 * The existence dimension of a layout translation — LT-1 (locked).
 *
 * Independent of lifecycle and synchronization. Answers only: does a layout exist
 * for this page translation, and is its reference sound?
 */
enum LayoutTranslationExistence: string
{
    /** No layout has been created for this page translation. */
    case NotCreated = 'not_created';

    /** Exactly one layout exists for this page translation. */
    case Exists = 'exists';

    /** A layout is referenced but the reference is broken or ambiguous (fail closed). */
    case InvalidReference = 'invalid_reference';
}
