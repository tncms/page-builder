<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Themes;

use Plugins\PageBuilder\Components\ComponentIdentifier;
use Plugins\PageBuilder\Interactive\InteractiveAsset;
use Plugins\PageBuilder\Interactive\InteractiveBehavior;
use Plugins\PageBuilder\Schema\Fields\ColorField;
use Plugins\PageBuilder\Schema\Fields\DataSourceField;
use Plugins\PageBuilder\Schema\Fields\Field;
use Plugins\PageBuilder\Schema\Fields\MediaField;
use Plugins\PageBuilder\Schema\Fields\NumberField;
use Plugins\PageBuilder\Schema\Fields\SelectField;
use Plugins\PageBuilder\Schema\Fields\TextareaField;
use Plugins\PageBuilder\Schema\Fields\TextField;
use Plugins\PageBuilder\Schema\Fields\ToggleField;
use Plugins\PageBuilder\Schema\Fields\UrlField;
use Plugins\PageBuilder\Schema\Schema;

/**
 * Turns one decoded theme component document into a {@see ThemeComponentDefinition} (contract
 * `pb-theme-component` v1, PB-XREG-4), or rejects it with a bounded diagnostic code.
 *
 * Strict: unknown keys are refused, so a theme cannot smuggle a view path, a server callable, or a data-source
 * registration. The schema is built through the same `Schema\Fields` classes PHP providers use, so the editor,
 * sanitizer and data-source pipeline see an identical shape.
 */
final class ThemeComponentFactory
{
    public const CONTRACT = '1';

    private const TOP_KEYS = ['contract', 'name', 'kind', 'label', 'category', 'icon', 'defaults', 'fields', 'allowedBlocks', 'behavior'];

    /** Keys whose presence is a capability/escape attempt rather than a typo. */
    private const DENIED_DATA_KEYS = ['dataSources', 'data_sources', 'data-sources', 'sources', 'source'];

    private const DENIED_VIEW_KEYS = ['view', 'views', 'render', 'template', 'blade', 'class', 'provider', 'callable', 'handler'];

    private const FIELD_KEYS = ['name', 'type', 'label', 'default', 'required', 'translatable', 'help', 'options', 'accepts'];

    private const FIELD_TYPES = [
        'text' => TextField::class,
        'textarea' => TextareaField::class,
        'select' => SelectField::class,
        'toggle' => ToggleField::class,
        'number' => NumberField::class,
        'url' => UrlField::class,
        'color' => ColorField::class,
        'media' => MediaField::class,
        'data-source' => DataSourceField::class,
    ];

    private const LOCALE = '/^[a-z]{2,3}([_-][A-Za-z0-9]{2,8})?$/';

    private const FIELD_NAME = '/^[a-z][a-z0-9_]{0,47}$/';

    private const ICON = '/^[a-z0-9][a-z0-9-]{0,47}$/';

    private const MAX_FIELDS = 32;

    private const MAX_LOCALES = 16;

    private const MAX_OPTIONS = 50;

    private const MAX_DEFAULTS_BYTES = 8192;

    /**
     * @param  array<string, mixed>  $document
     *
     * @throws ThemeComponentException
     */
    public function make(string $themeSlug, array $document): ThemeComponentDefinition
    {
        foreach (array_keys($document) as $key) {
            if (in_array($key, self::DENIED_DATA_KEYS, true)) {
                throw new ThemeComponentException('theme.capability_denied');
            }
            if (in_array($key, self::DENIED_VIEW_KEYS, true)) {
                throw new ThemeComponentException('theme.view_declared');
            }
            if (! in_array($key, self::TOP_KEYS, true)) {
                throw new ThemeComponentException('theme.component_invalid');
            }
        }

        if ((string) ($document['contract'] ?? '') !== self::CONTRACT) {
            throw new ThemeComponentException('theme.contract_unsupported');
        }

        $name = $document['name'] ?? null;
        if (! is_string($name) || strlen($name) > ComponentIdentifier::MAX_SEGMENT_LENGTH
            || preg_match(ComponentIdentifier::SEGMENT_PATTERN, $name) !== 1) {
            throw new ThemeComponentException('theme.name_invalid');
        }

        $kind = $document['kind'] ?? null;
        if (! in_array($kind, ['section', 'block'], true)) {
            throw new ThemeComponentException('theme.kind_invalid');
        }

        $labels = $this->labels($document['label'] ?? null, 80);
        $category = $this->category($document['category'] ?? 'theme');
        $icon = $this->icon($document['icon'] ?? null);
        $defaults = $this->defaults($document['defaults'] ?? []);
        $schema = $this->schema($document['fields'] ?? []);
        $allowed = $kind === 'section' ? $this->allowedBlocks($document['allowedBlocks'] ?? []) : [];

        if ($kind === 'block' && array_key_exists('allowedBlocks', $document)) {
            throw new ThemeComponentException('theme.component_invalid');
        }

        $behavior = array_key_exists('behavior', $document)
            ? $this->behavior($themeSlug, $name, $document['behavior'])
            : null;

        $class = match (true) {
            $kind === 'section' && $behavior !== null => ThemeInteractiveSectionComponent::class,
            $kind === 'section' => ThemeSectionComponent::class,
            $behavior !== null => ThemeInteractiveBlockComponent::class,
            default => ThemeBlockComponent::class,
        };

        return new $class($themeSlug, $name, $labels, $category, $icon, $defaults, $schema, $allowed, $behavior);
    }

    /** @return array<string, string> */
    private function labels(mixed $value, int $max): array
    {
        $map = is_string($value) ? ['en' => $value] : $value;

        if (! is_array($map) || $map === [] || array_is_list($map) || count($map) > self::MAX_LOCALES) {
            throw new ThemeComponentException('theme.label_invalid');
        }

        $labels = [];
        foreach ($map as $locale => $label) {
            if (! is_string($locale) || preg_match(self::LOCALE, $locale) !== 1 || ! $this->plainText($label, $max, false)) {
                throw new ThemeComponentException('theme.label_invalid');
            }
            $labels[$locale] = trim($label);
        }

        return $labels;
    }

    private function plainText(mixed $value, int $max, bool $allowEmpty): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);

        return ($allowEmpty || $value !== '')
            && mb_strlen($value) <= $max
            && preg_match('/[<>\p{Cc}]/u', $value) !== 1;
    }

    private function category(mixed $value): string
    {
        if (! is_string($value) || strlen($value) > 48 || preg_match(ComponentIdentifier::SEGMENT_PATTERN, $value) !== 1) {
            throw new ThemeComponentException('theme.component_invalid');
        }

        return $value;
    }

    private function icon(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || preg_match(self::ICON, $value) !== 1) {
            throw new ThemeComponentException('theme.component_invalid');
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function defaults(mixed $value): array
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value)) || ! $this->plainData($value, 0)) {
            throw new ThemeComponentException('theme.defaults_invalid');
        }

        if (strlen((string) json_encode($value)) > self::MAX_DEFAULTS_BYTES) {
            throw new ThemeComponentException('theme.defaults_invalid');
        }

        return $value;
    }

    private function plainData(mixed $value, int $depth): bool
    {
        if ($depth > 8) {
            return false;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if ((is_string($key) && strlen($key) > 64) || ! $this->plainData($item, $depth + 1)) {
                    return false;
                }
            }

            return true;
        }

        return $value === null || is_scalar($value);
    }

    /** @return array<string, mixed> */
    private function schema(mixed $fields): array
    {
        if (! is_array($fields) || ($fields !== [] && ! array_is_list($fields)) || count($fields) > self::MAX_FIELDS) {
            throw new ThemeComponentException('theme.field_invalid');
        }

        $built = [];
        $seen = [];
        foreach ($fields as $spec) {
            $field = $this->field($spec);
            if (isset($seen[$field->name()])) {
                throw new ThemeComponentException('theme.field_invalid');
            }
            $seen[$field->name()] = true;
            $built[] = $field;
        }

        return Schema::make()->fields($built)->toArray();
    }

    private function field(mixed $spec): Field
    {
        if (! is_array($spec) || array_is_list($spec) || array_diff(array_keys($spec), self::FIELD_KEYS) !== []) {
            throw new ThemeComponentException('theme.field_invalid');
        }

        $name = $spec['name'] ?? null;
        $type = $spec['type'] ?? null;
        if (! is_string($name) || preg_match(self::FIELD_NAME, $name) !== 1
            || ! is_string($type) || ! isset(self::FIELD_TYPES[$type])) {
            throw new ThemeComponentException('theme.field_invalid');
        }

        $class = self::FIELD_TYPES[$type];
        /** @var Field $field */
        $field = $class::make($name);
        $editor = [];

        if (array_key_exists('label', $spec)) {
            $labels = $this->labels($spec['label'], 80);
            $field->label($labels[app()->getLocale()] ?? $labels[array_key_first($labels)]);
            $editor['labels'] = $labels;
        }

        if (array_key_exists('help', $spec)) {
            if (! $this->plainText($spec['help'], 200, true)) {
                throw new ThemeComponentException('theme.field_invalid');
            }
            $field->help(trim($spec['help']));
        }

        if (array_key_exists('default', $spec)) {
            if (! $this->plainData($spec['default'], 0)) {
                throw new ThemeComponentException('theme.field_invalid');
            }
            $field->default($spec['default']);
        }

        foreach (['required', 'translatable'] as $flag) {
            if (array_key_exists($flag, $spec)) {
                if (! is_bool($spec[$flag])) {
                    throw new ThemeComponentException('theme.field_invalid');
                }
                $field->{$flag}($spec[$flag]);
            }
        }

        if (array_key_exists('options', $spec)) {
            $field->options($this->options($type, $spec['options']));
        }

        if ($type === 'data-source') {
            $editor['accepts'] = $this->accepts($spec['accepts'] ?? null);
        } elseif (array_key_exists('accepts', $spec)) {
            throw new ThemeComponentException('theme.field_invalid');
        }

        if ($editor !== []) {
            $field->editor($editor);
        }

        return $field;
    }

    /** @return array<string, string> */
    private function options(string $type, mixed $options): array
    {
        if ($type !== 'select' || ! is_array($options) || $options === [] || count($options) > self::MAX_OPTIONS) {
            throw new ThemeComponentException('theme.field_invalid');
        }

        $out = [];
        foreach ($options as $value => $label) {
            if (! is_string($label) && ! is_int($label)) {
                throw new ThemeComponentException('theme.field_invalid');
            }
            if (strlen((string) $value) > 64 || ! $this->plainText((string) $label, 80, false)) {
                throw new ThemeComponentException('theme.field_invalid');
            }
            $out[(string) $value] = trim((string) $label);
        }

        return $out;
    }

    /** @return list<string> */
    private function accepts(mixed $kinds): array
    {
        if (! is_array($kinds) || $kinds === [] || ! array_is_list($kinds) || count($kinds) > 8) {
            throw new ThemeComponentException('theme.field_invalid');
        }

        foreach ($kinds as $kind) {
            if (! is_string($kind) || preg_match('/^[a-z0-9]+([.-][a-z0-9]+)*$/', $kind) !== 1 || strlen($kind) > 64) {
                throw new ThemeComponentException('theme.field_invalid');
            }
        }

        return array_values(array_unique($kinds));
    }

    /** @return list<string> */
    private function allowedBlocks(mixed $value): array
    {
        if (! is_array($value) || ($value !== [] && ! array_is_list($value)) || count($value) > 64) {
            throw new ThemeComponentException('theme.component_invalid');
        }

        foreach ($value as $id) {
            if (! is_string($id) || ComponentIdentifier::classify($id) === ComponentIdentifier::KIND_INVALID) {
                throw new ThemeComponentException('theme.component_invalid');
            }
        }

        return array_values(array_unique($value));
    }

    private function behavior(string $themeSlug, string $name, mixed $spec): InteractiveBehavior
    {
        $keys = ['id', 'version', 'contexts', 'assets'];
        if (! is_array($spec) || array_is_list($spec) || array_diff(array_keys($spec), $keys) !== []) {
            throw new ThemeComponentException('theme.behavior_invalid');
        }

        $id = $spec['id'] ?? $themeSlug.':'.$name;
        $version = $spec['version'] ?? null;
        $contexts = $spec['contexts'] ?? InteractiveBehavior::CONTEXTS;
        $assets = $spec['assets'] ?? null;

        if (! is_string($id) || ! is_string($version) || ! is_array($contexts) || ! array_is_list($contexts)
            || ! is_array($assets) || ! array_is_list($assets) || count($assets) > InteractiveBehavior::MAX_ASSETS * 2) {
            throw new ThemeComponentException('theme.behavior_invalid');
        }

        $built = [];
        foreach ($assets as $asset) {
            if (! is_array($asset) || array_diff(array_keys($asset), ['kind', 'handle', 'path', 'deps']) !== []
                || ! is_string($asset['handle'] ?? null) || ! is_string($asset['path'] ?? null)
                || ! is_array($asset['deps'] ?? []) || ! array_is_list($asset['deps'] ?? [])) {
                throw new ThemeComponentException('theme.behavior_invalid');
            }

            $deps = array_values(array_map(static fn ($d): string => is_string($d) ? $d : '', $asset['deps'] ?? []));
            $built[] = match ($asset['kind'] ?? null) {
                InteractiveAsset::KIND_CSS => InteractiveAsset::css($asset['handle'], $asset['path'], $deps),
                InteractiveAsset::KIND_MODULE => InteractiveAsset::module($asset['handle'], $asset['path'], $deps),
                default => throw new ThemeComponentException('theme.behavior_invalid'),
            };
        }

        return new InteractiveBehavior($id, $version, $built, array_values(array_map('strval', $contexts)));
    }
}
