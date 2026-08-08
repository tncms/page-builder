<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Capture;

use Plugins\PageBuilder\Package\DesignPackageManifest;

/**
 * A deterministic Design Fingerprint (Phase 26.48, Rule 8).
 *
 * Computed ONLY from the normalized Builder JSON — never timestamps, preview,
 * runtime ids, metadata, or author, and never a media checksum. The same
 * normalized resource always yields the same fingerprint (duplicate detection,
 * and a stable handle for future updates / distribution). It reuses the package
 * layer's canonical JSON (lexicographic key sort) so ordering never affects it.
 */
final class DesignFingerprint
{
    public const ALGO = 'sha256';

    /**
     * @param  array<string, mixed>  $normalizedPayload
     */
    public static function of(array $normalizedPayload): string
    {
        return self::ALGO.'-'.hash(self::ALGO, DesignPackageManifest::canonicalJson($normalizedPayload));
    }
}
