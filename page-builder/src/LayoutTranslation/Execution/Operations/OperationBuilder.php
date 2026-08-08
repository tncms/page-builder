<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Operations;

use Plugins\PageBuilder\LayoutTranslation\Diff\NodeChangeType;
use Plugins\PageBuilder\LayoutTranslation\Execution\Acceptance\SynchronizationAcceptanceSet;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlan;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlanItem;

/**
 * Lowers accepted plan items into self-contained operations (LT-7B3, per LT-7-EXECUTION-ARCHITECTURE
 * §3.1).
 *
 * Layer: Translation (coordinator lowering). The ONLY bridge from a plan decision to a mutation: for each
 * item the reviewer accepted (`AcceptSource`), it maps the LT-5 `NodeChangeType` to a `SyncOperationKind`
 * and fetches the approved node bytes from the source, producing a `SyncOperation` that carries
 * everything the engine needs inline (INV-LT-7-OPS-02). Kept-target / ignored / unlisted items produce no
 * operation (fail-closed, D10). It reads the source (allowed at the coordinator layer); the engine does
 * not. It reinterprets no policy — the plan's classification is authority.
 */
final class OperationBuilder
{
    /**
     * @param  array<string, mixed>  $source
     */
    public function build(SynchronizationPlan $plan, SynchronizationAcceptanceSet $acceptance, array $source): SyncOperationSet
    {
        $index = SourceLayoutIndex::of($source);
        $operations = [];

        foreach ($plan->items() as $item) {
            $decision = $acceptance->decisionFor($item->origin);
            if ($decision === null || ! $decision->isAccepted()) {
                continue; // only explicitly accepted items are applied (D10)
            }

            $operations[] = $this->lower($item, $index);
        }

        return new SyncOperationSet($operations);
    }

    private function lower(SynchronizationPlanItem $item, SourceLayoutIndex $index): SyncOperation
    {
        return match ($item->sourceChange) {
            NodeChangeType::Removed => SyncOperation::removeNode($item->origin),
            NodeChangeType::Added => $this->insert($item, $index),
            NodeChangeType::Moved => $this->reposition($item, $index),
            NodeChangeType::StructureUpdated => SyncOperation::applyChildSet($item->origin, $this->entry($item, $index)['childOrigins']),
            NodeChangeType::ContentUpdated => SyncOperation::replaceOwnData($item->origin, $this->ownData($this->entry($item, $index)['node'])),
            default => throw new OperationLoweringException(OperationLoweringException::UNSUPPORTED_CHANGE, $item->origin),
        };
    }

    private function insert(SynchronizationPlanItem $item, SourceLayoutIndex $index): SyncOperation
    {
        $entry = $this->entry($item, $index);
        if ($entry['parent'] === null) {
            // Top-level section insertion has no parent — not expressible under the frozen LT-7B1 contract.
            throw new OperationLoweringException(OperationLoweringException::TOP_LEVEL_INSERT_UNSUPPORTED, $item->origin);
        }

        return SyncOperation::insertNode($item->origin, $entry['parent'], $entry['index'], $entry['node']);
    }

    private function reposition(SynchronizationPlanItem $item, SourceLayoutIndex $index): SyncOperation
    {
        $entry = $this->entry($item, $index);
        if ($entry['parent'] === null) {
            throw new OperationLoweringException(OperationLoweringException::TOP_LEVEL_INSERT_UNSUPPORTED, $item->origin);
        }

        return SyncOperation::repositionNode($item->origin, $entry['parent'], $entry['index']);
    }

    /**
     * @return array{node:array<string,mixed>, parent:?string, index:int, childOrigins:array<int,string>}
     */
    private function entry(SynchronizationPlanItem $item, SourceLayoutIndex $index): array
    {
        $entry = $index->get($item->origin);
        if ($entry === null) {
            throw new OperationLoweringException(OperationLoweringException::UNRESOLVED_SOURCE_NODE, $item->origin);
        }

        return $entry;
    }

    /**
     * The node's own content (type + data); the engine strips children and correspondence on apply.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function ownData(array $node): array
    {
        return [
            'type' => $node['type'] ?? null,
            'data' => is_array($node['data'] ?? null) ? $node['data'] : [],
        ];
    }
}
