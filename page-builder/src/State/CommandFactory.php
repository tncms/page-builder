<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\State;

use InvalidArgumentException;
use Plugins\PageBuilder\State\Commands\AddSectionCommand;
use Plugins\PageBuilder\State\Commands\ApplyColumnLayoutCommand;
use Plugins\PageBuilder\State\Commands\Command;
use Plugins\PageBuilder\State\Commands\CopyComponentCommand;
use Plugins\PageBuilder\State\Commands\CutComponentCommand;
use Plugins\PageBuilder\State\Commands\DuplicateComponentCommand;
use Plugins\PageBuilder\State\Commands\HoverComponentCommand;
use Plugins\PageBuilder\State\Commands\InsertComponentCommand;
use Plugins\PageBuilder\State\Commands\InsertPatternCommand;
use Plugins\PageBuilder\State\Commands\MoveComponentCommand;
use Plugins\PageBuilder\State\Commands\PasteComponentCommand;
use Plugins\PageBuilder\State\Commands\RedoCommand;
use Plugins\PageBuilder\State\Commands\RemoveComponentCommand;
use Plugins\PageBuilder\State\Commands\ReverseColumnsCommand;
use Plugins\PageBuilder\State\Commands\SelectComponentCommand;
use Plugins\PageBuilder\State\Commands\UndoCommand;
use Plugins\PageBuilder\State\Commands\UpdateComponentDataCommand;

/**
 * Builds a {@see Command} from a name + params map (Phase 8).
 *
 * This is the seam the future Vue editor and the state API use: a serialized
 * command ({name, payload}) becomes a concrete command object. Unknown names or
 * missing required params throw, so a bad request fails loudly rather than
 * silently no-op'ing.
 */
final class CommandFactory
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function make(string $name, array $params = []): Command
    {
        return match ($name) {
            'add-section' => new AddSectionCommand(
                $this->arr($params, 'section'),
                $this->nullableInt($params, 'index'),
            ),
            'remove-component' => new RemoveComponentCommand($this->str($params, 'component_id')),
            'duplicate-component' => new DuplicateComponentCommand($this->str($params, 'component_id')),
            'insert-component' => new InsertComponentCommand(
                $this->arr($params, 'component'),
                $this->nullableStr($params, 'target_parent_id'),
                $this->nullableInt($params, 'index'),
            ),
            'insert-pattern' => new InsertPatternCommand(
                $this->sectionList($params, 'sections'),
                $this->nullableInt($params, 'index'),
            ),
            'move-component' => new MoveComponentCommand(
                $this->str($params, 'component_id'),
                $this->nullableStr($params, 'target_parent_id'),
                (int) ($params['index'] ?? 0),
            ),
            'update-component-data' => new UpdateComponentDataCommand(
                $this->str($params, 'component_id'),
                $this->arr($params, 'data'),
            ),
            'apply-column-layout' => new ApplyColumnLayoutCommand(
                $this->str($params, 'container_id'),
                $this->widthList($params, 'widths'),
            ),
            'reverse-columns' => new ReverseColumnsCommand(
                $this->str($params, 'container_id'),
            ),
            'select-component' => new SelectComponentCommand($this->nullableStr($params, 'component_id')),
            'hover-component' => new HoverComponentCommand($this->nullableStr($params, 'component_id')),
            'copy-component' => new CopyComponentCommand($this->str($params, 'component_id')),
            'cut-component' => new CutComponentCommand($this->str($params, 'component_id')),
            'paste-component' => new PasteComponentCommand(
                $this->nullableStr($params, 'target_parent_id'),
                $this->nullableInt($params, 'index'),
            ),
            'undo' => new UndoCommand(),
            'redo' => new RedoCommand(),
            default => throw new InvalidArgumentException("Unknown page-builder command [{$name}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function str(array $params, string $key): string
    {
        $value = $params[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Command param [{$key}] must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function nullableStr(array $params, string $key): ?string
    {
        $value = $params[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function nullableInt(array $params, string $key): ?int
    {
        $value = $params[$key] ?? null;

        return is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function arr(array $params, string $key): array
    {
        $value = $params[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * A non-empty list of column widths (for apply-column-layout). Each entry is
     * coerced to a string; non-scalar entries are dropped. An empty result is
     * rejected — a layout with zero columns is never a valid preset.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, string>
     */
    private function widthList(array $params, string $key): array
    {
        $value = $params[$key] ?? null;
        $widths = [];

        if (is_array($value)) {
            foreach ($value as $entry) {
                if (is_string($entry) || is_int($entry) || is_float($entry)) {
                    $widths[] = (string) $entry;
                }
            }
        }

        if ($widths === []) {
            throw new InvalidArgumentException("Command param [{$key}] must be a non-empty list of widths.");
        }

        return $widths;
    }

    /**
     * A list of section arrays (for insert-pattern). Non-array entries dropped.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    private function sectionList(array $params, string $key): array
    {
        $value = $params[$key] ?? null;
        if (! is_array($value)) {
            throw new InvalidArgumentException("Command param [{$key}] must be an array of sections.");
        }

        return array_values(array_filter($value, 'is_array'));
    }
}
