<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Operations;

use RuntimeException;

/**
 * An accepted plan item could not be lowered to a self-contained operation (LT-7B3).
 *
 * Layer: Translation (coordinator lowering). Raised by the {@see OperationBuilder} when an accepted item
 * has an unsupported change type, references a source node that is absent, or would require a
 * not-yet-expressible operation (e.g. a top-level section insert). The coordinator normalizes it to an
 * `AcceptanceInvalid` outcome. The `$reason` is a stable discriminator, never content.
 */
final class OperationLoweringException extends RuntimeException
{
    public const UNSUPPORTED_CHANGE = 'unsupported_change';

    public const UNRESOLVED_SOURCE_NODE = 'unresolved_source_node';

    public const TOP_LEVEL_INSERT_UNSUPPORTED = 'top_level_insert_unsupported';

    public function __construct(
        public readonly string $reason,
        public readonly string $origin,
    ) {
        parent::__construct("Operation lowering failed: {$reason} at origin {$origin}");
    }
}
