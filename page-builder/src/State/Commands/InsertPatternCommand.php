<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;
use Plugins\PageBuilder\State\ComponentId;

/**
 * Insert a pattern's sections into the layout root as ONE command (Phase 25 §11).
 *
 * The sections come from a pattern's Builder payload — inserting a pattern is
 * exactly "import this Builder JSON". Every section (and its whole block subtree)
 * gets fresh ids so a pattern can be inserted repeatedly without collisions, and
 * the first inserted section is selected. Because this is a single command it is a
 * single undo entry, matching the rest of the editor.
 */
final class InsertPatternCommand extends AbstractCommand
{
    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function __construct(
        private readonly array $sections,
        private readonly ?int $index = null,
    ) {}

    public function name(): string
    {
        return 'insert-pattern';
    }

    public function label(): string
    {
        return 'Insert pattern';
    }

    public function execute(BuilderState $state): BuilderState
    {
        $payload = $state->payload();
        $at = $this->index;
        $firstId = null;

        foreach ($this->sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            // Fresh ids for the section + its entire block subtree.
            $section = ComponentId::regenerate($section, ComponentId::KIND_SECTION);
            $payload = $this->mutator()->addSection($payload, $section, $at);
            $firstId ??= (string) ($section['id'] ?? '');
            if ($at !== null) {
                $at++; // keep the pattern's own section order when inserting at an index
            }
        }

        $state->setPayload($payload);

        return $firstId !== null && $firstId !== '' ? $state->select($firstId) : $state;
    }

    /**
     * @return array{sections: array<int, array<string, mixed>>, index: int|null}
     */
    public function payload(): array
    {
        return ['sections' => $this->sections, 'index' => $this->index];
    }
}
