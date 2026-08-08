<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\Provenance;

/**
 * Which of the three provenance inputs drifted since the plan was built (LT-7B1).
 *
 * Layer: Translation (execution contract). The typed discriminator carried as the `detail` of a
 * `PlanStale` outcome (LT-7-FAILURE-MODEL). Checked in a fixed order — baseline, then source, then
 * target — so drift reporting is deterministic.
 */
enum ProvenanceDrift: string
{
    case BaselineChanged = 'BaselineChanged';

    case SourceChanged = 'SourceChanged';

    case TargetChanged = 'TargetChanged';
}
