<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources\Exceptions;

use RuntimeException;

/**
 * Thrown by the save gate when a component's data-source configuration fails
 * validation against its KNOWN source (PB-XREG-2). Carries only a bounded machine
 * reason plus the node id and field name — never provider internals.
 */
final class InvalidDataSourceConfigException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $nodeId,
        public readonly string $field,
    ) {
        parent::__construct('Invalid data-source configuration ('.$reason.') on field "'.$field.'".');
    }
}
