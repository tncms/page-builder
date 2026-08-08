<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Support;

use Plugins\PageBuilder\Patterns\Pattern;

/**
 * Serialises a pattern to a portable manifest + payload bundle (§16). Foundation
 * for the future marketplace export UI — no UI here, just deterministic
 * serialisation the packaging tooling can build on.
 */
final class PatternExporter
{
    /**
     * @return array{manifest: array<string, mixed>, payload: array<string, mixed>}
     */
    public function toArray(Pattern $pattern): array
    {
        return [
            'manifest' => $pattern->manifest()->toArray(),
            'payload' => $pattern->payload(),
        ];
    }

    public function manifestJson(Pattern $pattern): string
    {
        return $this->json($pattern->manifest()->toArray());
    }

    public function payloadJson(Pattern $pattern): string
    {
        return $this->json($pattern->payload());
    }

    /**
     * Write the bundle to a directory (manifest.json + payload.json). Returns the
     * files written. Thumbnails are copied when present.
     *
     * @return array<int, string>
     */
    public function toDirectory(Pattern $pattern, string $directory): array
    {
        $dir = rtrim($directory, '/\\');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $manifestPath = $dir.DIRECTORY_SEPARATOR.'manifest.json';
        $payloadPath = $dir.DIRECTORY_SEPARATOR.'payload.json';
        file_put_contents($manifestPath, $this->manifestJson($pattern));
        file_put_contents($payloadPath, $this->payloadJson($pattern));

        $written = [$manifestPath, $payloadPath];

        $thumb = $pattern->thumbnailPath();
        if ($thumb !== '' && is_file($thumb)) {
            $dest = $dir.DIRECTORY_SEPARATOR.$pattern->manifest()->thumbnail;
            copy($thumb, $dest);
            $written[] = $dest;
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
