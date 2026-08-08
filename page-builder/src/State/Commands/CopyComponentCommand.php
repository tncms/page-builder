<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;

/**
 * Copy a component's payload to the clipboard (copy mode). No payload change, so
 * no history snapshot and no dirty flag. A missing id is a no-op.
 */
final class CopyComponentCommand extends AbstractCommand
{
    public function __construct(private readonly string $componentId) {}

    public function name(): string
    {
        return 'copy-component';
    }

    public function label(): string
    {
        return 'Copy component';
    }

    public function execute(BuilderState $state): BuilderState
    {
        $found = $this->locator()->find($state->payload(), $this->componentId);

        if ($found !== null) {
            $state->clipboard()->copy($found['node']);
        }

        return $state;
    }

    /**
     * @return array{component_id: string}
     */
    public function payload(): array
    {
        return ['component_id' => $this->componentId];
    }
}
