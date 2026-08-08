<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

/**
 * The user-facing "Layout Translation" mode and the single effective on/off
 * decision (LT-M1 productization).
 *
 * The canonical switch is a plugin-owned setting persisted in the existing
 * cms_settings store under {@see SETTING_KEY} (Auto / Enabled / Disabled). `.env`
 * is only an optional developer/emergency override, never required for a normal
 * install. This class holds the pure precedence rule; the service provider wires
 * it to the real override + setting + Core locale count at boot and writes the
 * resulting boolean into config('page-builder.translation.enabled') — the one
 * value every runtime reader (resolver, renderer, controllers, projector) keeps
 * consuming, so no reader inspects .env or the settings store directly.
 */
final class LayoutTranslationSetting
{
    /** cms_settings key holding the Auto/Enabled/Disabled mode. */
    public const SETTING_KEY = 'page-builder.layout_translation';

    public const MODE_AUTO = 'auto';

    public const MODE_ENABLED = 'enabled';

    public const MODE_DISABLED = 'disabled';

    /** @var list<string> */
    public const MODES = [self::MODE_AUTO, self::MODE_ENABLED, self::MODE_DISABLED];

    /**
     * Coerce arbitrary stored/config input into a valid mode, defaulting to Auto.
     */
    public static function normalizeMode(mixed $mode): string
    {
        $mode = is_string($mode) ? strtolower(trim($mode)) : '';

        return in_array($mode, self::MODES, true) ? $mode : self::MODE_AUTO;
    }

    /**
     * Normalize the optional developer override into a strict tri-state: null
     * (defer to the setting), true (force on) or false (force off).
     */
    public static function normalizeOverride(mixed $override): ?bool
    {
        if ($override === null) {
            return null;
        }

        if (is_bool($override)) {
            return $override;
        }

        return filter_var($override, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * The single effective on/off decision. Precedence:
     *   1. explicit developer override (true/false) always wins;
     *   2. admin setting: Enabled → on, Disabled → off;
     *   3. Auto → on only when Core has more than one enabled locale.
     */
    public static function decide(?bool $override, string $mode, int $enabledLocaleCount): bool
    {
        if ($override !== null) {
            return $override;
        }

        return match (self::normalizeMode($mode)) {
            self::MODE_ENABLED => true,
            self::MODE_DISABLED => false,
            default => $enabledLocaleCount > 1,
        };
    }
}
