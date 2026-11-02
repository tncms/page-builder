<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Availability;

use RuntimeException;

/**
 * PB-XREG-4-R1 — a draft save that would change, move, remove, duplicate or forge a frozen unavailable
 * subtree, or introduce an UNKNOWN type. Carries only a bounded machine reason and the stable node id —
 * never a provider name, class, view, path or the node's data.
 */
final class UnavailableSubtreeViolation extends RuntimeException
{
    public const UNKNOWN_TYPE = 'unknown_type';

    public const FROZEN_CHANGED = 'frozen_changed';

    public const FROZEN_FORGED = 'frozen_forged';

    public const FROZEN_REMOVED = 'frozen_removed';

    public const FROZEN_DUPLICATED = 'frozen_duplicated';

    public const FROZEN_MOVED = 'frozen_moved';

    public const FROZEN_REORDERED = 'frozen_reordered';

    public const FROZEN_UNTRACKED = 'frozen_untracked';

    public function __construct(public readonly string $reason, public readonly string $nodeId = '')
    {
        parent::__construct('Unavailable component preservation refused ('.$reason.').');
    }
}
