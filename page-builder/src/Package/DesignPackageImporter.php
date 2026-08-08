<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Package;

use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Repository\Providers\ImportedResourceProvider;
use Plugins\PageBuilder\Repository\Rid;
use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * Validates and imports a `.tncms-design` package (FREE v1).
 *
 * Import is transport, not runtime: a valid package's resources are persisted via
 * {@see ImportedPackageStore} and thereafter served by
 * {@see ImportedResourceProvider} with `source = imported`. Resources are never
 * written to the repository index directly (Rule 1), never overwrite builtin/user
 * resources (source is forced to `imported`, Rule 7), and carry no runtime path.
 */
final class DesignPackageImporter
{
    private const EXPORTABLE = [Rid::KIND_BLOCK, Rid::KIND_SECTION, Rid::KIND_PAGE];

    public function __construct(
        private readonly ImportedPackageStore $store,
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
        private readonly DesignResourceRepositoryInterface $repository,
    ) {
    }

    /** Dry run: validate the package, persist nothing. */
    public function validate(string $json): DesignPackageReport
    {
        return $this->analyse($json);
    }

    /** Validate and, when clean, persist the package (atomic) + refresh the library. */
    public function import(string $json): DesignPackageReport
    {
        $report = $this->analyse($json);

        if (! $report->isImportable()) {
            return $report;
        }

        $decoded = json_decode($json, true);
        /** @var array<string, mixed> $decoded */
        $manifest = DesignPackageManifest::fromArray($decoded);

        $stored = $manifest->toArray();
        $stored['metadata'] = array_merge($manifest->metadata, ['source' => ImportedResourceProvider::SOURCE]);

        $this->store->save($this->storeId($report->rids), $stored);
        // Make the imported resources visible immediately.
        $this->repository->refresh();

        return new DesignPackageReport(
            ok: true,
            packageName: $report->packageName,
            resourceCount: $report->resourceCount,
            rids: $report->rids,
            blockers: [],
            warnings: $report->warnings,
            imported: true,
        );
    }

    private function analyse(string $json): DesignPackageReport
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return $this->fail('Design package', 0, [], [
                DesignPackageIssue::blocker('malformed_package', 'The file is not a valid Design Package (invalid JSON).'),
            ]);
        }

        $manifest = DesignPackageManifest::fromArray($decoded);
        $blockers = [];
        $warnings = [];

        if ($manifest->schemaVersion !== DesignPackageManifest::SCHEMA_VERSION) {
            $blockers[] = DesignPackageIssue::blocker('unsupported_schema', "Unsupported package schema version '{$manifest->schemaVersion}'.");
        }
        if ($manifest->type !== DesignPackageManifest::TYPE) {
            $blockers[] = DesignPackageIssue::blocker('invalid_type', 'This file is not a design-package.');
        }
        if ($manifest->resources === []) {
            $blockers[] = DesignPackageIssue::blocker('empty_package', 'The package contains no resources.');
        }
        if ($manifest->checksum === '') {
            $blockers[] = DesignPackageIssue::blocker('missing_checksum', 'The package has no checksum.');
        } elseif (! $manifest->checksumMatches()) {
            $blockers[] = DesignPackageIssue::blocker('checksum_mismatch', 'The package checksum does not match its contents.');
        }

        $rids = [];
        $seen = [];
        foreach ($manifest->resources as $index => $entry) {
            $this->validateResource($entry, $index, $seen, $rids, $blockers, $warnings);
        }

        return new DesignPackageReport(
            ok: $blockers === [],
            packageName: $manifest->name(),
            resourceCount: $manifest->resourceCount(),
            rids: array_values(array_unique($rids)),
            blockers: $blockers,
            warnings: $warnings,
        );
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, bool>  $seen
     * @param  array<int, string>  $rids
     * @param  array<int, DesignPackageIssue>  $blockers
     * @param  array<int, DesignPackageIssue>  $warnings
     */
    private function validateResource(array $entry, int $index, array &$seen, array &$rids, array &$blockers, array &$warnings): void
    {
        $where = "resource #".($index + 1);
        $kind = is_string($entry['kind'] ?? null) ? $entry['kind'] : '';
        $resourceId = is_string($entry['resourceId'] ?? null) ? $entry['resourceId'] : '';

        if (! in_array($kind, self::EXPORTABLE, true)) {
            $blockers[] = DesignPackageIssue::blocker('unsupported_kind', "{$where}: unsupported kind '{$kind}' (kits are not supported yet).");

            return;
        }

        $rid = ImportedResourceProvider::SOURCE.':'.$kind.':'.$resourceId;
        if (! Rid::isValid($rid)) {
            $blockers[] = DesignPackageIssue::blocker('invalid_rid', "{$where}: invalid resource id '{$resourceId}'.");

            return;
        }

        $dedupeKey = $kind.':'.$resourceId;
        if (isset($seen[$dedupeKey])) {
            $blockers[] = DesignPackageIssue::blocker('duplicate_resource', "{$where}: duplicate resource '{$dedupeKey}' in the package.");

            return;
        }
        $seen[$dedupeKey] = true;
        $rids[] = $rid;

        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : null;
        if ($payload === null || ($payload['schema'] ?? null) !== LayoutNormalizer::SCHEMA) {
            $blockers[] = DesignPackageIssue::blocker('schema_mismatch', "{$where}: the payload schema is missing or unsupported.");

            return;
        }

        $forbidden = DesignPackagePayload::forbiddenReferences($payload);
        if ($forbidden !== []) {
            $blockers[] = DesignPackageIssue::blocker('forbidden_reference', "{$where}: references host storage identities (".implode(', ', $forbidden).').', ['keys' => $forbidden]);
        }

        $unsafe = DesignPackagePayload::unsafeAssets($payload);
        if ($unsafe !== []) {
            $blockers[] = DesignPackageIssue::blocker('unsafe_asset', "{$where}: references an unsafe asset URL.", ['urls' => $unsafe]);
        }

        $missing = $this->missingComponents($payload);
        if ($missing !== []) {
            $blockers[] = DesignPackageIssue::blocker('unknown_components', "{$where}: uses components that are not installed.", ['components' => $missing]);
        }

        // Non-blocking advisories.
        $schemaVersion = $entry['compatibility']['schemaVersion'] ?? null;
        if (is_string($schemaVersion) && $schemaVersion !== '1') {
            $warnings[] = DesignPackageIssue::warning('schema_version', "{$where}: tree schema '{$schemaVersion}' is unverified; may need review.");
        }
        if (DesignPackagePayload::tokens($payload) !== []) {
            $warnings[] = DesignPackageIssue::warning('uses_tokens', "{$where}: uses design tokens that resolve at render time.");
        }
        if ($this->repository->has($rid)) {
            $warnings[] = DesignPackageIssue::warning('resource_replaced', "{$where}: an imported resource '{$rid}' already exists and will be replaced.");
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function missingComponents(array $payload): array
    {
        $types = DesignPackagePayload::componentTypes($payload);
        $missing = [];
        foreach ($types['sections'] as $type) {
            if (! $this->sections->has($type)) {
                $missing[] = "section:{$type}";
            }
        }
        foreach ($types['blocks'] as $type) {
            if (! $this->blocks->has($type)) {
                $missing[] = "block:{$type}";
            }
        }

        return $missing;
    }

    /**
     * @param  array<int, DesignPackageIssue>  $blockers
     */
    private function fail(string $name, int $count, array $rids, array $blockers): DesignPackageReport
    {
        return new DesignPackageReport(false, $name, $count, $rids, $blockers, []);
    }

    /**
     * Deterministic, collision-free store id for the package (kind-qualified so a
     * section and a page with the same slug never share a file).
     *
     * @param  array<int, string>  $rids
     */
    private function storeId(array $rids): string
    {
        sort($rids);
        if (count($rids) === 1) {
            $parts = explode(':', $rids[0]);

            return ($parts[1] ?? 'resource').'-'.($parts[2] ?? 'design');
        }

        return 'pack-'.substr(sha1(implode('|', $rids)), 0, 16);
    }
}
