<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

/**
 * The Compatibility Contract for a Resource Manifest (Phase 26.50A, Rules 5 & 9).
 *
 * Every resource DECLARES what it is compatible with — the platform NEVER infers
 * it. Declarations cover the Builder version constraint, theme compatibility, plugin
 * requirements and reserved future extensions, plus the minimum Builder version the
 * resource needs. This is metadata only: no resolution or enforcement happens here
 * (installation comes later — Rule 6).
 *
 * `"*"` means "any". Constraints use the familiar `^1.0` / `>=1.2` grammar; this
 * foundation stores them verbatim and does not evaluate them.
 */
final class CompatibilityMetadata
{
    public const ANY = '*';

    /**
     * @param  array<string, string>  $plugins  name => version constraint (requirements)
     * @param  array<string, string>  $extensions  reserved — name => version constraint
     */
    public function __construct(
        public readonly string $pageBuilder = self::ANY,
        public readonly string $theme = self::ANY,
        public readonly array $plugins = [],
        public readonly array $extensions = [],
        public readonly ?string $minimumBuilderVersion = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pageBuilder: is_string($data['page_builder'] ?? null) && $data['page_builder'] !== '' ? $data['page_builder'] : self::ANY,
            theme: is_string($data['theme'] ?? null) && $data['theme'] !== '' ? $data['theme'] : self::ANY,
            plugins: self::stringMap($data['plugins'] ?? []),
            extensions: self::stringMap($data['extensions'] ?? []),
            minimumBuilderVersion: is_string($data['minimum_builder_version'] ?? null) ? $data['minimum_builder_version'] : null,
        );
    }

    public function isThemeAgnostic(): bool
    {
        return $this->theme === self::ANY;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page_builder' => $this->pageBuilder,
            'theme' => $this->theme,
            'plugins' => $this->plugins,
            'extensions' => $this->extensions,
            'minimum_builder_version' => $this->minimumBuilderVersion,
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<string, string>
     */
    private static function stringMap($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $name => $constraint) {
            if (is_string($name) && is_string($constraint)) {
                $out[$name] = $constraint;
            }
        }

        return $out;
    }
}
