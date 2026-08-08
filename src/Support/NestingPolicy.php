<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * The one central composition nesting policy (Phase 25.3.2 §9/§10).
 *
 * A production builder must reject nonsensical nesting — an element dropped
 * straight into a Container, a Column inside a Column, a Container inside a
 * Column. Rather than a global type whitelist (which would reject legacy
 * sections and unknown/plugin block types), the policy only constrains the two
 * composition *container* types and stays permissive everywhere else:
 *
 *   - a Container holds ONLY Columns;
 *   - a Column holds ONLY ordinary elements (never a Column or a Container);
 *   - Sections, legacy blocks and unknown types keep their existing freedom.
 *
 * These two rules alone yield every rejection the phase requires:
 *   Element → Container ✗   Container → Container ✗
 *   Column  → Column    ✗   Container → Column    ✗
 * while allowing Section → Container, Container → Column and Column → Element.
 *
 * This is the single source consulted by the PHP mutation guard (LayoutMutator)
 * and mirrored 1:1 by the editor's `nestingPolicy.ts`.
 */
final class NestingPolicy
{
    public static function canContain(string $parentType, string $childType): bool
    {
        return match ($parentType) {
            'container' => $childType === 'column',
            'column' => $childType !== 'column' && $childType !== 'container',
            default => true,
        };
    }
}
