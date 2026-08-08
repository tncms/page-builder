<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Timeline\Replay;

/**
 * The verdict of a read-only replay (LT-9B, per LT-9A §3).
 *
 * Layer: Translation (timeline / replay). `Reproduced` — the recomputed target matched the recorded
 * outcome. `Diverged` — it did not (a tamper/corruption signal). `Unavailable` — required snapshots were
 * not retained, so no verdict is possible (fail-soft). Replay is observational only; it never executes
 * (INV-LT-9B-RT-02).
 */
enum ReplayStatus: string
{
    case Reproduced = 'reproduced';

    case Diverged = 'diverged';

    case Unavailable = 'unavailable';
}
