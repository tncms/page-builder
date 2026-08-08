<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Cloning\Results\SourceLayoutReadResult;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;

/**
 * Reads a source page translation's effective layout document (LT-3).
 *
 * Layer: Translation (contract). It selects the source payload by the deterministic
 * policy (published → draft → unavailable) and returns a domain result — never an
 * Eloquent model. Read-only; it never mutates the source.
 */
interface SourceLayoutDocumentReaderInterface
{
    public function read(PageTranslationKey $source): SourceLayoutReadResult;
}
