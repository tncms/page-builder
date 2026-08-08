<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Re-proportion a container's columns to a preset width list (Phase 26.1).
 *
 * The whole re-layout is a single command, so the CommandBus snapshots it as one
 * undo entry even though it may add or drop several columns at once. The
 * reconciliation (preserve/append/drop) lives in the mutator; this command only
 * carries the target container and the desired widths.
 */
final class ApplyColumnLayoutCommand extends AbstractCommand
{
    /**
     * @param  array<int, string>  $widths
     */
    public function __construct(
        private readonly string $containerId,
        private readonly array $widths,
    ) {}

    public function name(): string
    {
        return 'apply-column-layout';
    }

    public function label(): string
    {
        return 'Apply column layout';
    }

    public function execute(BuilderState $state): BuilderState
    {
        return $state->setPayload(
            $this->mutator()->applyColumnLayout($state->payload(), $this->containerId, $this->widths),
        );
    }

    /**
     * @return array{container_id: string, widths: array<int, string>}
     */
    public function payload(): array
    {
        return [
            'container_id' => $this->containerId,
            'widths' => $this->widths,
        ];
    }
}
