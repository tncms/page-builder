<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns;

use Illuminate\Support\Str;

/**
 * Typed, immutable description of a single pattern variable (Phase 25.1). Variables
 * are optional metadata on a manifest; when present they drive the "insert dialog"
 * and the {@see PatternVariableResolver} that turns a payload template into a plain
 * Builder payload. Types are declared here (not hardcoded in the editor) so the UI
 * renders whatever the manifest declares.
 */
final class PatternVariable
{
    /** Every supported variable type. The editor renders a field per type. */
    public const TYPES = ['text', 'textarea', 'url', 'image', 'color', 'select', 'toggle', 'number'];

    /**
     * @param  array<int, array{value: string, label: string}>  $options  For `select`.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type = 'text',
        public readonly string $label = '',
        public readonly mixed $default = '',
        public readonly bool $required = false,
        public readonly array $options = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $id = trim((string) ($data['id'] ?? ''));
        $type = (string) ($data['type'] ?? 'text');
        if (! in_array($type, self::TYPES, true)) {
            $type = 'text';
        }

        return new self(
            id: $id,
            type: $type,
            label: trim((string) ($data['label'] ?? '')) ?: Str::headline($id),
            default: $data['default'] ?? self::emptyDefault($type),
            required: (bool) ($data['required'] ?? false),
            options: self::normalizeOptions($data['options'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'default' => $this->default,
            'required' => $this->required,
            'options' => $this->options,
        ];
    }

    private static function emptyDefault(string $type): mixed
    {
        return match ($type) {
            'toggle' => false,
            'number' => 0,
            default => '',
        };
    }

    /**
     * Accepts `["a", "b"]` or `[{value,label}]` and normalises to a labelled list.
     *
     * @param  mixed  $value
     * @return array<int, array{value: string, label: string}>
     */
    private static function normalizeOptions(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $option) {
            if (is_array($option)) {
                $val = trim((string) ($option['value'] ?? ''));
                if ($val === '') {
                    continue;
                }
                $out[] = ['value' => $val, 'label' => trim((string) ($option['label'] ?? '')) ?: $val];
            } elseif (is_scalar($option)) {
                $val = trim((string) $option);
                if ($val !== '') {
                    $out[] = ['value' => $val, 'label' => $val];
                }
            }
        }

        return $out;
    }
}
