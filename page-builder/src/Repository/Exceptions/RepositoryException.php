<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Exceptions;

use RuntimeException;

/**
 * Base class for all Design Resource Repository errors. Consumers may catch this
 * to handle any repository failure; specific subclasses give precise reasons.
 */
abstract class RepositoryException extends RuntimeException
{
}
