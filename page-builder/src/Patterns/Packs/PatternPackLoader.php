<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Packs;

/**
 * Reads a `pack.json` at a source directory root into a {@see PatternPack}
 * (Phase 25.2, Part 3). Returns null when the directory is not a pack (no readable
 * pack.json or no id), so a plain pattern source discovers exactly as before.
 */
final class PatternPackLoader
{
    public function fromDirectory(string $directory): ?PatternPack
    {
        $file = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'pack.json';
        if (! is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);
        if (! is_array($data) || trim((string) ($data['id'] ?? '')) === '') {
            return null;
        }

        return PatternPack::fromArray($data, $directory);
    }
}
