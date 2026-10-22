<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a provider builds a record that is not bounded, plain, scalar data
 * (PB-XREG-2). The executor contains it as a bounded `invalid_result` error.
 */
final class InvalidDataSourceRecordException extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self('Invalid Page Builder data-source record: '.$reason);
    }
}
