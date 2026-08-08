<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts;

use Plugins\PageBuilder\LayoutTranslation\Cloning\Commands\CloneLayoutTranslationCommand;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Results\LayoutTranslationCloneResult;

/**
 * The application boundary of the clone use case (LT-3).
 *
 * Layer: Translation (application service). Callers authorize BEFORE invoking this
 * (capability/eligibility are not authorization). It owns the atomic transaction
 * and returns a typed result; it never publishes, baselines, diffs, synchronizes,
 * translates text, or performs UI/redirects.
 */
interface LayoutTranslationCloneServiceInterface
{
    public function cloneTranslation(CloneLayoutTranslationCommand $command): LayoutTranslationCloneResult;
}
