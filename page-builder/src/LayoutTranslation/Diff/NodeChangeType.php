<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Diff;

/**
 * The canonical structural change classes (LT-5, per frozen LT-0-ADR-001 D8).
 *
 * Layer: Translation (diff). LT-5 computes the structurally-determinable classes
 * (Added / Removed / Moved / StructureUpdated / ContentUpdated / Unchanged, plus
 * Conflicted at the three-way level). `StyleUpdated` and `SettingUpdated` are part
 * of the canonical model but are **reserved** — distinguishing them from content
 * changes requires a field taxonomy that does not yet exist; that refinement is
 * additive and does not change this enum's meaning.
 */
enum NodeChangeType: string
{
    case Added = 'added';

    case Removed = 'removed';

    case Moved = 'moved';

    case StructureUpdated = 'structure_updated';

    case StyleUpdated = 'style_updated';

    case SettingUpdated = 'setting_updated';

    case ContentUpdated = 'content_updated';

    case Unchanged = 'unchanged';

    case Conflicted = 'conflicted';
}
