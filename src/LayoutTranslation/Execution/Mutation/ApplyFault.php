<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Mutation;

/**
 * Why a single operation could not be applied to the target (LT-7B2).
 *
 * Layer: Translation (mutation engine). A closed, baseline-free set of structural faults the pure
 * applier can raise. All normalize to a `CorrespondenceFault` outcome at the LT-7B3 service boundary;
 * kept granular here for the engine's own clarity. The engine never inspects the Source, Baseline or
 * Plan (INV-LT-7-OPS-02), so every fault is decidable from the Target + operation alone.
 */
enum ApplyFault: string
{
    /** The operation's target origin does not exist in the current target. */
    case NodeNotFound = 'node_not_found';

    /** An insert/reposition parent origin does not exist in the current target. */
    case ParentNotFound = 'parent_not_found';

    /** An insert would introduce an origin that already exists (ambiguous correspondence). */
    case DuplicateOrigin = 'duplicate_origin';

    /** A remove would delete a subtree containing target-owned nodes (INV-LT-03). */
    case TargetOwnedRemoval = 'target_owned_removal';
}
