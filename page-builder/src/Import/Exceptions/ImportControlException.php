<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Import\Exceptions;

use RuntimeException;

/**
 * Internal control-flow exceptions used to abort and roll back a commit
 * transaction. They never escape the service — the caller receives a
 * FullPageImportResult with issues instead.
 */
class ImportControlException extends RuntimeException
{
    public function __construct(public readonly string $reasonCode, string $message)
    {
        parent::__construct($message);
    }
}
