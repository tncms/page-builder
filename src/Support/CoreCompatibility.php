<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use TheNguyen\CMS\Support\CmsInfo;

/**
 * Core-compatibility authority for the Page Builder plugin (PB RC-B).
 *
 * TN CMS Core does not enforce a plugin's `plugin.json` `requires.tncms`
 * constraint — the {@see \TheNguyen\CMS\Support\Plugin} value object treats it as
 * descriptive metadata and the {@see \TheNguyen\CMS\Services\PluginLifecycleManager}
 * activation flow never version-checks it. This class is the plugin-owned
 * enforcement of that same constraint, consumed by the service provider so an
 * unsupported Core fails closed instead of running half-supported.
 *
 * The comparison is SemVer-aware ({@see version_compare}), never lexical, so the
 * prerelease ordering (…beta.7.1.21 < …beta.7.1.22 < …beta.7.1.23 < …beta.7.1.23.x
 * < …beta.8 < 1.0.0) is respected. The {@see CmsInfo} reference is guarded so
 * plugin discovery never fatals on a Core old enough to predate the class.
 */
final class CoreCompatibility
{
    /**
     * Minimum TN CMS Core this Page Builder release supports.
     *
     * The RC-B heading contract depends on the Core page-title visibility seam
     * (`show_page_title`) introduced in 1.0.0-beta.7.1.23; on older Core the
     * contract cannot be honoured, so the plugin refuses to boot. Mirrors the
     * plugin.json `requires.tncms` constraint ({@see requirement()}).
     */
    public const MINIMUM_CORE = '1.0.0-beta.7.1.23';

    /**
     * Test-only override of the detected Core version. `null` (default) means
     * "detect from {@see CmsInfo}"; an empty string simulates a Core with no
     * version marker (the class-absent, fail-closed case); any other value
     * simulates that Core version. Lets the accepted (`.23`) and missing-marker
     * provider paths be proven deterministically against the pinned dev Core.
     * A test MUST reset it to null in tearDown.
     *
     * @internal
     */
    private static ?string $detectedVersionOverride = null;

    /** @internal Test seam — see {@see $detectedVersionOverride}. */
    public static function overrideDetectedVersionForTesting(?string $version): void
    {
        self::$detectedVersionOverride = $version;
    }

    /** The manifest constraint string this minimum represents (`>=…`). */
    public static function requirement(): string
    {
        return '>='.self::MINIMUM_CORE;
    }

    /**
     * The running Core version, or null when the version marker is absent — a
     * Core old enough to predate {@see CmsInfo}, treated as incompatible. The
     * class reference is guarded so discovery never fatals on such a Core.
     */
    public static function detectedVersion(): ?string
    {
        if (self::$detectedVersionOverride !== null) {
            return self::$detectedVersionOverride === '' ? null : self::$detectedVersionOverride;
        }

        if (! class_exists(CmsInfo::class)) {
            return null;
        }

        $version = CmsInfo::VERSION;

        return $version !== '' ? $version : null;
    }

    /**
     * Whether a given Core version satisfies the minimum, using SemVer-aware
     * prerelease comparison (never lexical).
     */
    public static function meets(string $coreVersion): bool
    {
        return version_compare($coreVersion, self::MINIMUM_CORE, '>=');
    }

    /** Whether the running Core satisfies the minimum. */
    public static function satisfied(): bool
    {
        $version = self::detectedVersion();

        return $version !== null && self::meets($version);
    }

    /**
     * Deterministic, administrator-facing explanation of an incompatibility, used
     * for the recorded plugin error and the Core boot log.
     */
    public static function incompatibilityMessage(): string
    {
        $detected = self::detectedVersion() ?? 'an unknown (pre-'.self::MINIMUM_CORE.') version';

        return sprintf(
            'Page Builder %s requires TN CMS Core %s. The running Core is %s, so the plugin stays inactive until Core is updated.',
            PageBuilderManager::VERSION,
            self::requirement(),
            $detected,
        );
    }
}
