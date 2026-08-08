<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Operations;

/**
 * The concrete kind of a single execution operation (LT-7B1, per LT-7-EXECUTION-ARCHITECTURE §3.1).
 *
 * Layer: Translation (execution contract). A closed set of node-scoped target mutations a future
 * Mutation Engine (LT-7B2) may perform. Each kind is lowered deterministically from an accepted LT-6
 * plan item; this enum carries no policy — it only names the operation and describes its own required
 * shape. The mapping from a `NodeChangeType` is the OperationBuilder's concern (LT-7B2), never this
 * enum's.
 */
enum SyncOperationKind: string
{
    /** Insert an approved source node under a corresponded parent at a sibling position. */
    case InsertNode = 'insert_node';

    /** Remove the corresponded node from the target. */
    case RemoveNode = 'remove_node';

    /** Move the corresponded target node to a new parent/position (content untouched). */
    case RepositionNode = 'reposition_node';

    /** Set the corresponded node's child origin set/order from the source. */
    case ApplyChildSet = 'apply_child_set';

    /** Overwrite the corresponded node's own data from the source (descendants preserved). */
    case ReplaceOwnData = 'replace_own_data';

    /** Whether this kind carries the approved source node payload fragment. */
    public function carriesNodePayload(): bool
    {
        return $this === self::InsertNode || $this === self::ReplaceOwnData;
    }

    /** Whether this kind carries a parent/position placement. */
    public function carriesPlacement(): bool
    {
        return $this === self::InsertNode || $this === self::RepositionNode;
    }

    /** Whether this kind carries an ordered child origin set. */
    public function carriesChildOrigins(): bool
    {
        return $this === self::ApplyChildSet;
    }
}
