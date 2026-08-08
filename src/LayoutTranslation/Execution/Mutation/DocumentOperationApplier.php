<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Mutation;

use Plugins\PageBuilder\LayoutTranslation\Correspondence\NodeCorrespondence;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\OperationApplierInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Operations\SyncOperation;
use Plugins\PageBuilder\LayoutTranslation\Execution\Operations\SyncOperationKind;
use Plugins\PageBuilder\LayoutTranslation\Execution\Operations\SyncOperationSet;

/**
 * The pure mutation engine: Target + SyncOperation[] → Target′ (LT-7B2, per LT-7-EXECUTION-ARCHITECTURE
 * §3.3).
 *
 * Layer: Translation (mutation engine). Deterministic and side-effect-free — it reads ONLY the target
 * payload and the operations (INV-LT-7-OPS-02): no Source, Baseline, Plan, repository, transaction,
 * persistence, history or diagnostics. The working copy STARTS as the target and only the nodes named by
 * operations are touched (INV-LT-02); every other node — target-owned or untouched — is copied through
 * verbatim (INV-LT-03). Nodes are matched by correspondence origin (LT-4). Operations are applied in the
 * order the `SyncOperationSet` presents them (origin-sorted; ordering is the set's authority, never the
 * engine's — INV-LT-7-OPS-03). Any unresolvable operation throws and no partial document is produced.
 *
 * Placement note: the layout node tree is `sections[] → blocks[] (section children) → data.blocks[]
 * (block children)`. Top-level section insertion has no parent and is therefore not expressible under
 * the frozen LT-7B1 `InsertNode(parentOrigin …)` contract; that is a documented boundary for a later
 * increment, not a silent gap — such an operation cannot be constructed.
 */
final class DocumentOperationApplier implements OperationApplierInterface
{
    public function apply(array $target, SyncOperationSet $operations): array
    {
        $t = $target;
        foreach ($operations->all() as $operation) {
            $t = $this->applyOne($t, $operation);
        }

        return $t;
    }

    /**
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    private function applyOne(array $t, SyncOperation $op): array
    {
        return match ($op->kind) {
            SyncOperationKind::RemoveNode => $this->remove($t, $op->origin),
            SyncOperationKind::InsertNode => $this->insert($t, (string) $op->parentOrigin, (int) $op->position, $op->nodePayload ?? []),
            SyncOperationKind::RepositionNode => $this->reposition($t, $op->origin, (string) $op->parentOrigin, (int) $op->position),
            SyncOperationKind::ApplyChildSet => $this->applyChildSet($t, $op->origin, $op->childOrigins ?? []),
            SyncOperationKind::ReplaceOwnData => $this->replaceOwnData($t, $op->origin, $op->nodePayload ?? []),
        };
    }

    /**
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    private function remove(array $t, string $origin): array
    {
        $found = false;
        $sections = $this->walkReplace($this->sections($t), true, $origin, function (array $node, bool $isSection) use ($origin): ?array {
            if ($this->subtreeHasTargetOwned($node, $isSection)) {
                throw new OperationApplicationException(ApplyFault::TargetOwnedRemoval, $origin);
            }

            return null;
        }, $found);

        if (! $found) {
            throw new OperationApplicationException(ApplyFault::NodeNotFound, $origin);
        }

        return $this->withSections($t, $sections);
    }

    /**
     * @param  array<string, mixed>  $t
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function insert(array $t, string $parentOrigin, int $position, array $node): array
    {
        $newOrigin = NodeCorrespondence::originOf($node);
        if ($newOrigin !== null && in_array($newOrigin, $this->allOrigins($t), true)) {
            throw new OperationApplicationException(ApplyFault::DuplicateOrigin, $newOrigin);
        }

        return $this->insertUnderParent($t, $parentOrigin, $position, $node);
    }

    /**
     * @param  array<string, mixed>  $t
     * @return array<string, mixed>
     */
    private function reposition(array $t, string $origin, string $parentOrigin, int $position): array
    {
        $captured = null;
        $found = false;
        $sections = $this->walkReplace($this->sections($t), true, $origin, function (array $node) use (&$captured): ?array {
            $captured = $node;

            return null;
        }, $found);

        if (! $found || $captured === null) {
            throw new OperationApplicationException(ApplyFault::NodeNotFound, $origin);
        }

        // Re-insert the whole node (subtree preserved), so no target-owned guard is needed for a move.
        return $this->insertUnderParent($this->withSections($t, $sections), $parentOrigin, $position, $captured);
    }

    /**
     * @param  array<string, mixed>  $t
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function insertUnderParent(array $t, string $parentOrigin, int $position, array $node): array
    {
        $found = false;
        $sections = $this->walkReplace($this->sections($t), true, $parentOrigin, function (array $parent, bool $isSection) use ($node, $position): array {
            $children = $this->childrenOf($parent, $isSection);
            $pos = max(0, min($position, count($children)));
            array_splice($children, $pos, 0, [$node]);

            return $this->withChildren($parent, $isSection, $children);
        }, $found);

        if (! $found) {
            throw new OperationApplicationException(ApplyFault::ParentNotFound, $parentOrigin);
        }

        return $this->withSections($t, $sections);
    }

    /**
     * @param  array<string, mixed>  $t
     * @param  array<int, string>  $childOrigins
     * @return array<string, mixed>
     */
    private function applyChildSet(array $t, string $origin, array $childOrigins): array
    {
        $found = false;
        $sections = $this->walkReplace($this->sections($t), true, $origin, function (array $parent, bool $isSection) use ($childOrigins): array {
            $byOrigin = [];
            $rest = [];
            foreach ($this->childrenOf($parent, $isSection) as $child) {
                $childOrigin = is_array($child) ? NodeCorrespondence::originOf($child) : null;
                if ($childOrigin !== null && in_array($childOrigin, $childOrigins, true)) {
                    $byOrigin[$childOrigin] = $child;
                } else {
                    $rest[] = $child; // target-owned / unmentioned children are preserved (INV-LT-03)
                }
            }

            $ordered = [];
            foreach ($childOrigins as $childOrigin) {
                if (isset($byOrigin[$childOrigin])) {
                    $ordered[] = $byOrigin[$childOrigin];
                }
            }

            return $this->withChildren($parent, $isSection, array_merge($ordered, $rest));
        }, $found);

        if (! $found) {
            throw new OperationApplicationException(ApplyFault::NodeNotFound, $origin);
        }

        return $this->withSections($t, $sections);
    }

    /**
     * @param  array<string, mixed>  $t
     * @param  array<string, mixed>  $ownData
     * @return array<string, mixed>
     */
    private function replaceOwnData(array $t, string $origin, array $ownData): array
    {
        $found = false;
        $sections = $this->walkReplace($this->sections($t), true, $origin, function (array $node) use ($ownData): array {
            $existing = is_array($node['data'] ?? null) ? $node['data'] : [];
            $incoming = is_array($ownData['data'] ?? null) ? $ownData['data'] : [];

            // Own data only: never carry the source's children or correspondence over the target's.
            unset($incoming['blocks'], $incoming[NodeCorrespondence::ORIGIN_KEY]);
            if (array_key_exists(NodeCorrespondence::ORIGIN_KEY, $existing)) {
                $incoming[NodeCorrespondence::ORIGIN_KEY] = $existing[NodeCorrespondence::ORIGIN_KEY];
            }
            if (array_key_exists('blocks', $existing)) {
                $incoming['blocks'] = $existing['blocks']; // preserve target block-children subtree
            }

            $node['data'] = $incoming;
            if (array_key_exists('type', $ownData)) {
                $node['type'] = $ownData['type'];
            }

            return $node; // section children (node['blocks']) are untouched → preserved
        }, $found);

        if (! $found) {
            throw new OperationApplicationException(ApplyFault::NodeNotFound, $origin);
        }

        return $this->withSections($t, $sections);
    }

    /**
     * Rebuild a node list, applying $visitor to the first node whose origin matches; recurse otherwise.
     * $visitor(array $node, bool $isSection): ?array — a replacement node, or null to delete it.
     *
     * @param  array<int, mixed>  $nodes
     * @return array<int, mixed>
     */
    private function walkReplace(array $nodes, bool $isSection, string $origin, callable $visitor, bool &$found): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                $out[] = $node;

                continue;
            }

            if (! $found && NodeCorrespondence::originOf($node) === $origin) {
                $found = true;
                $replacement = $visitor($node, $isSection);
                if ($replacement !== null) {
                    $out[] = $replacement;
                }

                continue;
            }

            $children = $this->childrenOf($node, $isSection);
            if ($children !== []) {
                $node = $this->withChildren($node, $isSection, $this->walkReplace($children, false, $origin, $visitor, $found));
            }

            $out[] = $node;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<int, mixed>
     */
    private function childrenOf(array $node, bool $isSection): array
    {
        if ($isSection) {
            return is_array($node['blocks'] ?? null) ? array_values($node['blocks']) : [];
        }

        return is_array($node['data']['blocks'] ?? null) ? array_values($node['data']['blocks']) : [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<int, mixed>  $children
     * @return array<string, mixed>
     */
    private function withChildren(array $node, bool $isSection, array $children): array
    {
        if ($isSection) {
            $node['blocks'] = array_values($children);

            return $node;
        }

        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $data['blocks'] = array_values($children);
        $node['data'] = $data;

        return $node;
    }

    private function subtreeHasTargetOwned(array $node, bool $isSection): bool
    {
        foreach ($this->childrenOf($node, $isSection) as $child) {
            if (! is_array($child)) {
                continue;
            }
            if ($this->isTargetOwned($child)) {
                return true;
            }
            if ($this->subtreeHasTargetOwned($child, false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A node is target-owned when it carries no STAMPED correspondence origin (LT-4/D6) — it was added
     * independently of the source and must never be auto-removed. A physical id is not a correspondence
     * stamp, so `originOf`'s id fallback must not be used here.
     *
     * @param  array<string, mixed>  $node
     */
    private function isTargetOwned(array $node): bool
    {
        $origin = $node['data'][NodeCorrespondence::ORIGIN_KEY] ?? null;

        return ! is_string($origin) || trim($origin) === '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function allOrigins(array $payload): array
    {
        $origins = [];
        $this->collectOrigins($this->sections($payload), true, $origins);

        return $origins;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @param  array<int, string>  $origins
     */
    private function collectOrigins(array $nodes, bool $isSection, array &$origins): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            $origin = NodeCorrespondence::originOf($node);
            if ($origin !== null) {
                $origins[] = $origin;
            }
            $this->collectOrigins($this->childrenOf($node, $isSection), false, $origins);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, mixed>
     */
    private function sections(array $payload): array
    {
        return is_array($payload['sections'] ?? null) ? array_values($payload['sections']) : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, mixed>  $sections
     * @return array<string, mixed>
     */
    private function withSections(array $payload, array $sections): array
    {
        $payload['sections'] = array_values($sections);

        return $payload;
    }
}
