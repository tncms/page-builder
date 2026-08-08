<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Dependencies;

use Illuminate\Support\Str;

/**
 * A single, UI-ready dependency requirement (Phase 25.2.x, Part 1). Describes one
 * thing a pattern needs — a plugin, a component, or a capability — with a stable
 * `type`/`id`, a human `label`, its resolved `status` (met | missing), and the
 * suggested `action` to satisfy it (install | enable | upgrade). This is METADATA
 * ONLY: it powers a "requirements" UI, it does NOT perform installation.
 */
final class PatternRequirement
{
    public const STATUS_MET = 'met';

    public const STATUS_MISSING = 'missing';

    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly string $label,
        public readonly string $status,
        public readonly string $action,
    ) {}

    /**
     * Build a requirement for a declared dependency, deriving a friendly label and
     * a type-appropriate action.
     */
    public static function make(string $type, string $id, bool $met): self
    {
        return new self(
            type: $type,
            id: $id,
            label: self::labelFor($type, $id),
            status: $met ? self::STATUS_MET : self::STATUS_MISSING,
            action: $met ? '' : self::actionFor($type),
        );
    }

    public function met(): bool
    {
        return $this->status === self::STATUS_MET;
    }

    /**
     * @return array{type: string, id: string, label: string, status: string, action: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'label' => $this->label,
            'status' => $this->status,
            'action' => $this->action,
        ];
    }

    private static function labelFor(string $type, string $id): string
    {
        $name = Str::headline(str_replace(['.', '_', '-'], ' ', $id));

        return match ($type) {
            'plugin' => "{$name} Plugin",
            'component' => "{$name} Component",
            'capability' => "{$name} Capability",
            default => $name,
        };
    }

    private static function actionFor(string $type): string
    {
        return match ($type) {
            'plugin' => 'install',
            'component' => 'enable',
            'capability' => 'upgrade',
            default => 'install',
        };
    }
}
