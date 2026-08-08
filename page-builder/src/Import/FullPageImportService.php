<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Import;

use Illuminate\Support\Facades\DB;
use Plugins\PageBuilder\Import\Exceptions\ImportControlException;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\Html;
use Plugins\PageBuilder\Support\LayoutNormalizer;
use Plugins\PageBuilder\Support\LayoutStore;
use Throwable;

/**
 * Imports a `page` Design Resource into an existing document's Builder DRAFT.
 *
 * Resources are resolved exclusively through the repository; the published
 * payload, editor mode, SEO, slug, and title are never touched. The commit is
 * atomic — any failure leaves the original draft byte-identical.
 *
 * Frozen contracts are untouched: this is a persistence-layer operation over
 * {@see LayoutStore::saveDraft()}, not a runtime/BuilderState/CommandBus change.
 */
final class FullPageImportService
{
    private const SUPPORTED_SCHEMA = LayoutNormalizer::SCHEMA;
    private const ASSET_KEYS = ['src', 'url', 'href', 'image', 'background', 'logo', 'avatar', 'poster', 'button_url'];

    public function __construct(
        private readonly DesignResourceRepositoryInterface $repository,
        private readonly LayoutStore $layouts,
        private readonly LayoutNormalizer $normalizer,
        private readonly SectionRegistry $sections,
        private readonly BlockRegistry $blocks,
        private readonly NodeIdGenerator $ids,
    ) {
    }

    // ---- Dry run (deterministic, read-only, mutation-free) ------------------

    public function plan(FullPageImportRequest $request): FullPageImportPlan
    {
        $blockers = [];
        $warnings = [];

        $resource = $this->repository->tryFind($request->rid);

        $currentDraft = $this->normalizer->normalize($this->currentDraft($request));
        [$currentSections, $currentBlocks] = $this->counts($currentDraft);
        $revision = $this->revisionOf($this->currentDraft($request));

        // Target ownership (validation #10).
        if (! EditableOwners::isSupported($request->ownerType) || EditableOwners::resolve($request->ownerType, $request->ownerId) === null) {
            $blockers[] = FullPageImportIssue::blocker('target_not_found', 'The target document was not found.');
        }

        if ($resource === null) {
            $blockers[] = FullPageImportIssue::blocker('resource_not_found', "Resource not found: {$request->rid}.");

            return $this->plan_empty($request, $currentSections, $currentBlocks, $revision, $blockers, $warnings);
        }

        if ($resource->kind !== 'page') {
            $blockers[] = FullPageImportIssue::blocker('wrong_kind', "Only page templates can be imported (got '{$resource->kind}').");
        }

        $incoming = $this->normalizer->normalize($resource->payload);
        [$incomingSections, $incomingBlocks] = $this->counts($incoming);

        if ($incomingSections === 0) {
            $blockers[] = FullPageImportIssue::blocker('empty_template', 'The template has no sections to import.');
        }
        if (($resource->payload['schema'] ?? self::SUPPORTED_SCHEMA) !== self::SUPPORTED_SCHEMA) {
            $blockers[] = FullPageImportIssue::blocker('schema_mismatch', 'The template payload schema is not supported.');
        }

        $components = $this->scanComponents($incoming);
        $compatibility = $this->checkCompatibility($resource, $components, $blockers, $warnings);
        $dependencies = $this->checkDependencies($resource, $components, $blockers);
        $assets = $this->scanAssets($incoming, $blockers);
        $tokens = $this->scanTokens($incoming, $warnings);

        return new FullPageImportPlan(
            rid: $resource->rid,
            version: $resource->version,
            kind: $resource->kind,
            targetOwnerType: $request->ownerType,
            targetOwnerId: $request->ownerId,
            currentSectionCount: $currentSections,
            currentBlockCount: $currentBlocks,
            incomingSectionCount: $incomingSections,
            incomingBlockCount: $incomingBlocks,
            compatibility: $compatibility,
            dependencies: $dependencies,
            assets: $assets,
            tokens: $tokens,
            warnings: $warnings,
            blockers: $blockers,
            planHash: $this->planHash($resource->rid, $resource->version, $incoming),
            revision: $revision,
        );
    }

    // ---- Atomic commit ------------------------------------------------------

    public function import(FullPageImportRequest $request): FullPageImportResult
    {
        // 1–2. Resolve + validate again via the plan.
        $plan = $this->plan($request);
        $snapshot = $this->snapshot($request);

        if (! $plan->ok()) {
            return FullPageImportResult::failed($request->rid, $plan->planHash, $snapshot, $plan->blockers);
        }

        // 3. Verify the confirmed plan.
        if ($request->expectedPlanHash !== null && $request->expectedPlanHash !== $plan->planHash) {
            return FullPageImportResult::failed($request->rid, $plan->planHash, $snapshot, [
                FullPageImportIssue::blocker('plan_mismatch', 'The template changed since the preview. Please review again.'),
            ]);
        }

        // 4. Verify revision (optimistic concurrency).
        if ($request->expectedRevision !== null && $request->expectedRevision !== $plan->revision) {
            return FullPageImportResult::failed($request->rid, $plan->planHash, $snapshot, [
                FullPageImportIssue::blocker('stale_revision', 'The draft changed since the preview. Please review again.'),
            ]);
        }

        try {
            return DB::transaction(function () use ($request, $plan): FullPageImportResult {
                // Re-read inside the transaction and re-check the revision.
                $currentInTx = $this->currentDraft($request);
                $revisionInTx = $this->revisionOf($currentInTx);
                if ($request->expectedRevision !== null && $request->expectedRevision !== $revisionInTx) {
                    throw new ImportControlException('stale_revision', 'Draft changed during commit.');
                }

                // 7. Snapshot the old draft.
                $snapshot = new FullPageImportSnapshot($request->ownerType, $request->ownerId, $currentInTx, $revisionInTx);

                // 1. Resolve again. 6. Build canonical payload. 5. Fresh node IDs.
                $resource = $this->repository->find($request->rid);
                $incoming = $this->normalizer->normalize($resource->payload);
                $final = $this->ids->assign($incoming);

                // 8. Replace once (draft only — never published/mode/SEO/slug).
                $this->layouts->saveDraft($request->ownerType, $request->ownerId, $final, $request->actorId);

                // 9. Verify.
                $saved = $this->layouts->getDraftPayload($request->ownerType, $request->ownerId);
                [$savedSections, $savedBlocks] = $this->counts($this->normalizer->normalize($saved));
                if ($savedSections !== $plan->incomingSectionCount || ! $this->hasUniqueIds($saved)) {
                    throw new ImportControlException('verify_failed', 'Post-import verification failed.');
                }

                return new FullPageImportResult(
                    ok: true,
                    rid: $resource->rid,
                    planHash: $plan->planHash,
                    replaced: true,
                    snapshot: $snapshot,
                    issues: $plan->warnings,
                    resultSectionCount: $savedSections,
                    resultBlockCount: $savedBlocks,
                    revision: $this->revisionOf($saved),
                );
            });
        } catch (ImportControlException $e) {
            return FullPageImportResult::failed($request->rid, $plan->planHash, $snapshot, [
                FullPageImportIssue::blocker($e->reasonCode, $e->getMessage()),
            ]);
        } catch (Throwable $e) {
            report($e);

            return FullPageImportResult::failed($request->rid, $plan->planHash, $snapshot, [
                FullPageImportIssue::blocker('commit_failed', 'The import could not be completed; the draft is unchanged.'),
            ]);
        }
    }

    // ---- Helpers ------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private function currentDraft(FullPageImportRequest $request): ?array
    {
        return $this->layouts->getDraftPayload($request->ownerType, $request->ownerId);
    }

    private function snapshot(FullPageImportRequest $request): FullPageImportSnapshot
    {
        $draft = $this->currentDraft($request);

        return new FullPageImportSnapshot($request->ownerType, $request->ownerId, $draft, $this->revisionOf($draft));
    }

    /**
     * @param  array<int, FullPageImportIssue>  $blockers
     * @param  array<int, FullPageImportIssue>  $warnings
     */
    private function plan_empty(FullPageImportRequest $request, int $currentSections, int $currentBlocks, string $revision, array $blockers, array $warnings): FullPageImportPlan
    {
        return new FullPageImportPlan(
            rid: $request->rid, version: '0.0.0', kind: 'unknown',
            targetOwnerType: $request->ownerType, targetOwnerId: $request->ownerId,
            currentSectionCount: $currentSections, currentBlockCount: $currentBlocks,
            incomingSectionCount: 0, incomingBlockCount: 0,
            compatibility: [], dependencies: [], assets: [], tokens: [],
            warnings: $warnings, blockers: $blockers,
            planHash: $this->planHash($request->rid, '0.0.0', ['sections' => []]),
            revision: $revision,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{0: int, 1: int}
     */
    private function counts(?array $payload): array
    {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        $sectionCount = 0;
        $blockCount = 0;

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }
            $sectionCount++;
            $blockCount += $this->countBlocks(is_array($section['blocks'] ?? null) ? $section['blocks'] : []);
        }

        return [$sectionCount, $blockCount];
    }

    /**
     * @param  array<int, mixed>  $blocks
     */
    private function countBlocks(array $blocks): int
    {
        $count = 0;
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $count++;
            $nested = $block['data']['blocks'] ?? null;
            if (is_array($nested)) {
                $count += $this->countBlocks($nested);
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{sections: array<int, string>, blocks: array<int, string>}
     */
    private function scanComponents(array $payload): array
    {
        $sectionTypes = [];
        $blockTypes = [];

        foreach (($payload['sections'] ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }
            if (is_string($section['type'] ?? null)) {
                $sectionTypes[$section['type']] = true;
            }
            $this->collectBlockTypes(is_array($section['blocks'] ?? null) ? $section['blocks'] : [], $blockTypes);
        }

        return ['sections' => array_keys($sectionTypes), 'blocks' => array_keys($blockTypes)];
    }

    /**
     * @param  array<int, mixed>  $blocks
     * @param  array<string, bool>  $out
     */
    private function collectBlockTypes(array $blocks, array &$out): void
    {
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            if (is_string($block['type'] ?? null)) {
                $out[$block['type']] = true;
            }
            $nested = $block['data']['blocks'] ?? null;
            if (is_array($nested)) {
                $this->collectBlockTypes($nested, $out);
            }
        }
    }

    /**
     * @param  array{sections: array<int, string>, blocks: array<int, string>}  $components
     * @param  array<int, FullPageImportIssue>  $blockers
     * @param  array<int, FullPageImportIssue>  $warnings
     * @return array<string, mixed>
     */
    private function checkCompatibility(DesignResource $resource, array $components, array &$blockers, array &$warnings): array
    {
        $missing = [];
        foreach ($components['sections'] as $type) {
            if (! $this->sections->has($type)) {
                $missing[] = "section:{$type}";
            }
        }
        foreach ($components['blocks'] as $type) {
            if (! $this->blocks->has($type)) {
                $missing[] = "block:{$type}";
            }
        }

        if ($missing !== []) {
            $blockers[] = FullPageImportIssue::blocker('unknown_components', 'The template uses components that are not installed.', ['components' => $missing]);
        }

        $compat = $resource->compatibility ?? [];
        $schemaVersion = $compat['schemaVersion'] ?? null;
        if ($schemaVersion !== null && $schemaVersion !== '1') {
            $warnings[] = FullPageImportIssue::warning('schema_version', "Template tree schema '{$schemaVersion}' is unverified; import may need review.");
        }

        foreach ((array) ($compat['requiredFeatures'] ?? []) as $feature) {
            $warnings[] = FullPageImportIssue::warning('unverified_feature', "Required feature '{$feature}' could not be verified.");
        }

        return [
            'schemaVersion' => $schemaVersion,
            'builder' => $compat['builder'] ?? null,
            'requiredComponents' => $compat['requiredComponents'] ?? [],
            'requiredFeatures' => $compat['requiredFeatures'] ?? [],
            'missingComponents' => $missing,
            'satisfied' => $missing === [],
        ];
    }

    /**
     * @param  array{sections: array<int, string>, blocks: array<int, string>}  $components
     * @param  array<int, FullPageImportIssue>  $blockers
     * @return array<int, array<string, mixed>>
     */
    private function checkDependencies(DesignResource $resource, array $components, array &$blockers): array
    {
        $deps = [];

        // A page depends on the components it uses (already gated above) and on any
        // referenced member resources (pages carry none; guarded for kits later).
        foreach ($resource->members as $memberRid) {
            $available = $this->repository->tryFind($memberRid) !== null;
            $deps[] = ['rid' => $memberRid, 'satisfied' => $available];
            if (! $available) {
                $blockers[] = FullPageImportIssue::blocker('missing_dependency', "Required resource is missing: {$memberRid}.", ['rid' => $memberRid]);
            }
        }

        foreach ($components['sections'] as $type) {
            $deps[] = ['component' => "section:{$type}", 'satisfied' => $this->sections->has($type)];
        }
        foreach ($components['blocks'] as $type) {
            $deps[] = ['component' => "block:{$type}", 'satisfied' => $this->blocks->has($type)];
        }

        return $deps;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, FullPageImportIssue>  $blockers
     * @return array<int, string>
     */
    private function scanAssets(array $payload, array &$blockers): array
    {
        $assets = [];
        $unsafe = [];
        $this->walkData($payload, function (string $key, string $value) use (&$assets, &$unsafe): void {
            if (! in_array($key, self::ASSET_KEYS, true) || $value === '') {
                return;
            }
            $safe = Html::safeUrl($value, '__unsafe__');
            if ($safe === '__unsafe__') {
                $unsafe[] = $value;
            } else {
                $assets[$value] = true;
            }
        });

        if ($unsafe !== []) {
            $blockers[] = FullPageImportIssue::blocker('unsafe_asset', 'The template references an unsafe asset URL.', ['urls' => array_values($unsafe)]);
        }

        $list = array_keys($assets);
        sort($list);

        return $list;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, FullPageImportIssue>  $warnings
     * @return array<int, string>
     */
    private function scanTokens(array $payload, array &$warnings): array
    {
        $tokens = [];
        $this->walkData($payload, function (string $key, string $value) use (&$tokens): void {
            if (preg_match('/^\{\{\s*token\.([a-z0-9_.\-]+)\s*\}\}$/i', trim($value), $m)) {
                $tokens[$m[1]] = true;
            } elseif (str_starts_with($value, 'token:')) {
                $tokens[substr($value, 6)] = true;
            }
        });

        $list = array_keys($tokens);
        sort($list);

        // Tokens resolve at render time with fallbacks; unresolved ones only warn.
        return $list;
    }

    /**
     * Walk every string value in every node's data (recursively).
     *
     * @param  array<string, mixed>  $payload
     * @param  callable(string, string): void  $visit
     */
    private function walkData(array $payload, callable $visit): void
    {
        foreach (($payload['sections'] ?? []) as $section) {
            if (is_array($section)) {
                $this->walkNodeData($section, $visit);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  callable(string, string): void  $visit
     */
    private function walkNodeData(array $node, callable $visit): void
    {
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];
        $this->walkArray($data, $visit);

        foreach (($node['blocks'] ?? []) as $block) {
            if (is_array($block)) {
                $this->walkNodeData($block, $visit);
            }
        }
        $nested = $node['data']['blocks'] ?? null;
        if (is_array($nested)) {
            foreach ($nested as $child) {
                if (is_array($child)) {
                    $this->walkNodeData($child, $visit);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  callable(string, string): void  $visit
     */
    private function walkArray(array $data, callable $visit): void
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && is_string($key)) {
                $visit($key, $value);
            } elseif (is_array($value) && $key !== 'blocks') {
                $this->walkArray($value, $visit);
            }
        }
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function hasUniqueIds(?array $payload): bool
    {
        $ids = [];
        $ok = true;
        foreach (($payload['sections'] ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }
            $this->collectIds($section, $ids, $ok);
        }

        return $ok && $ids !== [];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, bool>  $ids
     */
    private function collectIds(array $node, array &$ids, bool &$ok): void
    {
        $id = $node['id'] ?? null;
        if (! is_string($id) || $id === '' || isset($ids[$id])) {
            $ok = false;
        } elseif (is_string($id)) {
            $ids[$id] = true;
        }

        foreach (($node['blocks'] ?? []) as $block) {
            if (is_array($block)) {
                $this->collectIds($block, $ids, $ok);
            }
        }
        $nested = $node['data']['blocks'] ?? null;
        if (is_array($nested)) {
            foreach ($nested as $child) {
                if (is_array($child)) {
                    $this->collectIds($child, $ids, $ok);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $incoming
     */
    private function planHash(string $rid, string $version, array $incoming): string
    {
        return sha1((string) json_encode([
            'rid' => $rid,
            'version' => $version,
            'tree' => $this->stripIds($incoming),
        ], JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function stripIds(array $payload): array
    {
        $sections = [];
        foreach (($payload['sections'] ?? []) as $section) {
            if (is_array($section)) {
                $sections[] = $this->stripNodeId($section);
            }
        }
        $payload['sections'] = $sections;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function stripNodeId(array $node): array
    {
        unset($node['id']);
        if (isset($node['blocks']) && is_array($node['blocks'])) {
            $node['blocks'] = array_map(fn ($b) => is_array($b) ? $this->stripNodeId($b) : $b, $node['blocks']);
        }
        if (isset($node['data']['blocks']) && is_array($node['data']['blocks'])) {
            $node['data']['blocks'] = array_map(fn ($b) => is_array($b) ? $this->stripNodeId($b) : $b, $node['data']['blocks']);
        }

        return $node;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function revisionOf(?array $payload): string
    {
        return sha1((string) json_encode($payload ?? [], JSON_UNESCAPED_SLASHES));
    }
}
