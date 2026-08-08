<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

/**
 * Serializes an immutable {@see ResourceManifest} to its canonical wire form
 * (Phase 26.50A, Rules 2 & 14).
 *
 * Serialization is DETERMINISTIC — the same manifest always produces byte-identical
 * output — so manifests are stable in a portable package, an offline bundle or an
 * enterprise mirror. It is the exact inverse of {@see ManifestParser}:
 * parse(serialize(m)) ≡ m.
 */
final class ManifestSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(ResourceManifest $manifest): array
    {
        return $manifest->toArray();
    }

    public function toJson(ResourceManifest $manifest, bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return (string) json_encode($manifest->toArray(), $flags);
    }
}
