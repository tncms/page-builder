<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Baseline\Services;

use RuntimeException;

/**
 * Internal signal to roll back a baseline capture on an integrity fault (LT-4).
 *
 * Layer: Translation (baseline / service internal). Thrown inside the transaction
 * so all writes roll back; the service maps it to a typed outcome. It never escapes
 * the service boundary.
 */
final class BaselineIntegrityException extends RuntimeException
{
}
