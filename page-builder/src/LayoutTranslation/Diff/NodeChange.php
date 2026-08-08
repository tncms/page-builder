<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Diff;

/**
 * An immutable per-node structural change, keyed by correspondence origin (LT-5).
 *
 * Layer: Translation (diff / value). Carries only the origin and the change class —
 * no payload, so the diff is payload-safe (never logs or exposes content).
 */
final class NodeChange
{
    public function __construct(
        public readonly string $origin,
        public readonly NodeChangeType $type,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['origin' => $this->origin, 'type' => $this->type->value];
    }
}
