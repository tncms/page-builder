<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Support;

/**
 * Deterministic content checksum for a pattern bundle (Phase 25.2.x, Part 6). Used
 * by the installation-metadata seam / future integrity checks. Keys are sorted so
 * the hash is stable regardless of JSON key order.
 */
final class PatternChecksum
{
    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $payload
     */
    public static function forBundle(array $manifest, array $payload): string
    {
        return self::hash(['manifest' => $manifest, 'payload' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function forArray(array $data): string
    {
        return self::hash($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function hash(array $data): string
    {
        self::ksortRecursive($data);

        return hash('sha256', (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function ksortRecursive(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }
}
