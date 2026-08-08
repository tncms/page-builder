<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Support;

use InvalidArgumentException;
use Plugins\PageBuilder\Patterns\Pattern;
use Plugins\PageBuilder\Patterns\PatternLoader;
use Plugins\PageBuilder\Patterns\PatternManifest;
use Plugins\PageBuilder\Patterns\PatternValidator;

/**
 * Loads + validates a pattern from a manifest/payload pair or a directory (§16).
 * The marketplace / theme-pack tooling will reuse this to ingest external packs;
 * an invalid manifest or payload throws rather than producing a broken pattern.
 */
final class PatternImporter
{
    public function __construct(
        private readonly PatternValidator $validator,
        private readonly PatternLoader $loader,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $payload
     */
    public function fromArray(array $manifest, array $payload, string $directory = ''): Pattern
    {
        // Full bundle validation (§14/§15): manifest + schema + preview + variables +
        // payload + placeholder cross-check. Warnings (e.g. unused variables) never
        // block import; fatal errors (e.g. unknown placeholders) do.
        $result = $this->validator->validateBundle($manifest, $payload);
        if ($result['errors'] !== []) {
            throw new InvalidArgumentException('Invalid pattern: '.implode('; ', $result['errors']));
        }

        return new Pattern(
            manifest: PatternManifest::fromArray($manifest, (string) ($manifest['source'] ?? 'import')),
            payloadLoader: fn (): array => $payload,
            directory: $directory,
            sourceId: 'import',
        );
    }

    public function fromDirectory(string $directory): Pattern
    {
        $manifestFile = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.'manifest.json';
        $manifest = $this->readJson($manifestFile);
        if ($manifest === null) {
            throw new InvalidArgumentException("Missing or invalid manifest.json in [{$directory}].");
        }

        return $this->fromArray($manifest, $this->loader->loadPayload($directory), $directory);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $file): ?array
    {
        if (! is_file($file)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : null;
    }
}
