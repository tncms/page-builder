<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Operations;

use InvalidArgumentException;

/**
 * One immutable, self-contained execution operation (LT-7B1, per LT-7-EXECUTION-ARCHITECTURE §3.2).
 *
 * Layer: Translation (execution contract). Pure execution data — the single unit a future Mutation
 * Engine consumes to mutate a target node. Per INV-LT-7-OPS-01 it is immutable, deterministic and
 * fully serializable, and it holds NO reference to the Source, Target, Baseline, any repository,
 * callback or closure. All information required to apply it — including the approved source node
 * payload — lives inside the operation (INV-LT-7-OPS-02), so the engine never re-reads the Source,
 * Baseline or Plan.
 *
 * Instances are built only through the per-kind named constructors, which enforce the shape each kind
 * requires; the constructor is private so a malformed operation cannot exist.
 */
final class SyncOperation
{
    /**
     * @param  array<string, mixed>|null  $nodePayload   approved source node fragment (payload-bearing kinds only)
     * @param  array<int, string>|null    $childOrigins  ordered child origin set (ApplyChildSet only)
     */
    private function __construct(
        public readonly SyncOperationKind $kind,
        public readonly string $origin,
        public readonly ?string $parentOrigin,
        public readonly ?int $position,
        public readonly ?array $nodePayload,
        public readonly ?array $childOrigins,
    ) {}

    /**
     * @param  array<string, mixed>  $nodePayload
     */
    public static function insertNode(string $origin, string $parentOrigin, int $position, array $nodePayload): self
    {
        self::assertOrigin($origin);
        self::assertOrigin($parentOrigin, 'parent origin');

        return new self(SyncOperationKind::InsertNode, $origin, $parentOrigin, $position, $nodePayload, null);
    }

    public static function removeNode(string $origin): self
    {
        self::assertOrigin($origin);

        return new self(SyncOperationKind::RemoveNode, $origin, null, null, null, null);
    }

    public static function repositionNode(string $origin, string $parentOrigin, int $position): self
    {
        self::assertOrigin($origin);
        self::assertOrigin($parentOrigin, 'parent origin');

        return new self(SyncOperationKind::RepositionNode, $origin, $parentOrigin, $position, null, null);
    }

    /**
     * @param  array<int, string>  $childOrigins
     */
    public static function applyChildSet(string $origin, array $childOrigins): self
    {
        self::assertOrigin($origin);

        $normalized = array_values($childOrigins);
        foreach ($normalized as $child) {
            self::assertOrigin($child, 'child origin');
        }

        return new self(SyncOperationKind::ApplyChildSet, $origin, null, null, null, $normalized);
    }

    /**
     * @param  array<string, mixed>  $nodePayload
     */
    public static function replaceOwnData(string $origin, array $nodePayload): self
    {
        self::assertOrigin($origin);

        return new self(SyncOperationKind::ReplaceOwnData, $origin, null, null, $nodePayload, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'origin' => $this->origin,
            'parent_origin' => $this->parentOrigin,
            'position' => $this->position,
            'node_payload' => $this->nodePayload,
            'child_origins' => $this->childOrigins,
        ];
    }

    /**
     * Reconstruct an operation from its serialized form (INV-LT-7-OPS-01 round-trip).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $kind = SyncOperationKind::from((string) ($data['kind'] ?? ''));
        $origin = (string) ($data['origin'] ?? '');

        return match ($kind) {
            SyncOperationKind::InsertNode => self::insertNode(
                $origin,
                (string) ($data['parent_origin'] ?? ''),
                (int) ($data['position'] ?? 0),
                self::payloadFrom($data),
            ),
            SyncOperationKind::RemoveNode => self::removeNode($origin),
            SyncOperationKind::RepositionNode => self::repositionNode(
                $origin,
                (string) ($data['parent_origin'] ?? ''),
                (int) ($data['position'] ?? 0),
            ),
            SyncOperationKind::ApplyChildSet => self::applyChildSet(
                $origin,
                is_array($data['child_origins'] ?? null) ? $data['child_origins'] : [],
            ),
            SyncOperationKind::ReplaceOwnData => self::replaceOwnData($origin, self::payloadFrom($data)),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function payloadFrom(array $data): array
    {
        $payload = $data['node_payload'] ?? null;
        if (! is_array($payload)) {
            throw new InvalidArgumentException('SyncOperation payload is required for this kind.');
        }

        return $payload;
    }

    private static function assertOrigin(string $origin, string $label = 'origin'): void
    {
        if ($origin === '') {
            throw new InvalidArgumentException("SyncOperation {$label} must be a non-empty string.");
        }
    }
}
