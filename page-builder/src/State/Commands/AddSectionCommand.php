<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State\Commands;

use Plugins\PageBuilder\State\BuilderState;
use Plugins\PageBuilder\State\ComponentId;

/**
 * Add a section to the layout root at an optional index, then select it.
 */
final class AddSectionCommand extends AbstractCommand
{
    /**
     * @param  array<string, mixed>  $section
     */
    public function __construct(
        private readonly array $section,
        private readonly ?int $index = null,
    ) {}

    public function name(): string
    {
        return 'add-section';
    }

    public function label(): string
    {
        return 'Add section';
    }

    public function execute(BuilderState $state): BuilderState
    {
        // Pre-assign an id so we can select the new section after insertion.
        $section = $this->section;
        if (! is_string($section['id'] ?? null) || trim((string) $section['id']) === '') {
            $section['id'] = ComponentId::generate();
        }

        $state->setPayload($this->mutator()->addSection($state->payload(), $section, $this->index));

        return $state->select($section['id']);
    }

    /**
     * @return array{section: array<string, mixed>, index: int|null}
     */
    public function payload(): array
    {
        return ['section' => $this->section, 'index' => $this->index];
    }
}
