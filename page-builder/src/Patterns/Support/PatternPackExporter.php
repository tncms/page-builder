<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Support;

use Plugins\PageBuilder\Patterns\Pattern;
use Plugins\PageBuilder\Patterns\Packs\PatternPack;

/**
 * Serialises a whole pattern pack to a portable bundle (Phase 25.2.x, Part 5),
 * building on {@see PatternExporter} for the individual patterns. Layout on disk:
 *
 *   <dir>/pack.json                  — pack metadata
 *   <dir>/patterns/<id>.json         — { manifest, payload } per pattern
 *   <dir>/screenshots/<id>.<ext>     — copied preview images (when present)
 *
 * Foundation for the marketplace/theme-pack tooling — deterministic serialisation,
 * no UI.
 */
final class PatternPackExporter
{
    public function __construct(private readonly PatternExporter $patterns) {}

    /**
     * @param  array<int, Pattern>  $patterns
     * @return array{pack: array<string, mixed>, patterns: array<int, array{manifest: array<string, mixed>, payload: array<string, mixed>}>}
     */
    public function toArray(PatternPack $pack, array $patterns): array
    {
        return [
            'pack' => $pack->toArray(),
            'patterns' => array_map(fn (Pattern $pattern): array => $this->patterns->toArray($pattern), $patterns),
        ];
    }

    /**
     * Write the pack bundle to a directory. Returns the files written.
     *
     * @param  array<int, Pattern>  $patterns
     * @return array<int, string>
     */
    public function toDirectory(PatternPack $pack, array $patterns, string $directory): array
    {
        $dir = rtrim($directory, '/\\');
        $patternsDir = $dir.DIRECTORY_SEPARATOR.'patterns';
        $shotsDir = $dir.DIRECTORY_SEPARATOR.'screenshots';
        foreach ([$dir, $patternsDir, $shotsDir] as $target) {
            if (! is_dir($target)) {
                mkdir($target, 0775, true);
            }
        }

        $packFile = $dir.DIRECTORY_SEPARATOR.'pack.json';
        file_put_contents($packFile, $this->json($pack->toArray()));
        $written = [$packFile];

        foreach ($patterns as $pattern) {
            $bundleFile = $patternsDir.DIRECTORY_SEPARATOR.$pattern->id().'.json';
            file_put_contents($bundleFile, $this->json($this->patterns->toArray($pattern)));
            $written[] = $bundleFile;

            $thumb = $pattern->thumbnailPath();
            if ($thumb !== '' && is_file($thumb)) {
                $dest = $shotsDir.DIRECTORY_SEPARATOR.$pattern->id().'.'.pathinfo($thumb, PATHINFO_EXTENSION);
                copy($thumb, $dest);
                $written[] = $dest;
            }
        }

        return $written;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
