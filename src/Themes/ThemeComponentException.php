<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use RuntimeException;

/**
 * A theme component batch was rejected (PB-XREG-4). The message IS a bounded diagnostic code — never a path,
 * file content, or class name — so it is safe to surface in editor diagnostics and test kits.
 */
final class ThemeComponentException extends RuntimeException
{
    public function __construct(public readonly string $diagnostic)
    {
        parent::__construct($diagnostic);
    }
}
