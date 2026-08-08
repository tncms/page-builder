<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\PageTemplate\Exceptions;

use Plugins\PageBuilder\Import\FullPageImportIssue;
use RuntimeException;

/**
 * Thrown inside the create-and-apply transaction when the Full Page import step
 * fails, so the enclosing DB transaction rolls back the just-created draft
 * content (no half-applied Builder layout survives). Carries the import issues
 * so the caller can report them. Internal to TemplateApplicationService.
 */
final class TemplateApplicationFailed extends RuntimeException
{
    /**
     * @param  array<int, FullPageImportIssue>  $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct('Template application failed.');
    }
}
