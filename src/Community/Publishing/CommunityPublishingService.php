<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Publishing;

use Plugins\PageBuilder\Community\CommunityResourceStore;
use Plugins\PageBuilder\Community\Manifest\ManifestParser;
use Plugins\PageBuilder\Community\Manifest\ResourceManifest;
use Plugins\PageBuilder\Community\Publishing\Exceptions\PublishingException;
use Plugins\PageBuilder\Repository\Rid;

/**
 * The official Community publishing workflow service (Phase 26.50B, Rule 4).
 *
 * It moves a local draft through the canonical lifecycle — validate → package →
 * publish → deprecate → archive (+ restore) — recording an immutable publishing
 * history and generating immutable package metadata. It orchestrates the
 * deterministic {@see PublishingPipeline}, the {@see ResourceLifecycle} state
 * machine and local {@see CommunityResourceStore} persistence.
 *
 * It NEVER synchronizes, uploads, downloads, installs, resolves dependencies or
 * touches commerce (Rules 4, 18) — publishing is a workflow, not a transport. The
 * identical workflow is reused by Marketplace, Enterprise and PRO; only
 * authorization differs (Rule 15).
 *
 * @see docs/community-publishing.md
 */
final class CommunityPublishingService
{
    public function __construct(
        private readonly CommunityResourceStore $store,
        private readonly PublishingPipeline $pipeline = new PublishingPipeline,
        private readonly ResourceLifecycle $lifecycle = new ResourceLifecycle,
        private readonly ManifestParser $parser = new ManifestParser,
    ) {}

    /**
     * Register a new local Draft from a captured manifest + payload. A published
     * manifest is immutable — re-drafting one is refused (Rule 14).
     *
     * @param  array<string, mixed>  $manifestArray
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $preview
     */
    public function draft(array $manifestArray, array $payload, array $preview = []): PublishingResult
    {
        $manifest = $this->parser->fromArray($manifestArray);

        if (! Rid::isValid($manifest->rid) || $manifest->provider !== CommunityResourceStore::SOURCE) {
            return PublishingResult::fail(PublishingResult::STEP_MANIFEST, ["'{$manifest->rid}' is not a valid community resource identity."]);
        }

        // A published version's manifest is immutable (Rule 14): re-drafting the same
        // (or an older) version is refused. A NEW, greater version is allowed — that
        // is exactly Working Copy → Capture → New Publish. History is preserved.
        $existing = $this->store->find($manifest->rid);
        if ($existing !== null) {
            $latest = $this->historyOf($existing)->latestVersion();
            if ($latest !== null && version_compare($manifest->version, $latest, '<=')) {
                throw PublishingException::manifestLocked($manifest->rid);
            }
        }

        $slug = Rid::parse($manifest->rid)->resourceId;
        $this->store->write($slug, $manifestArray, $payload, $preview);
        $this->store->writeLifecycle($slug, [
            'status' => ResourceStatus::Draft->value,
            'restore_to' => null,
            'history' => $existing['lifecycle']['history'] ?? [],
            'package' => null,
            'pending_package' => null,
        ]);

        return PublishingResult::ok(ResourceStatus::Draft);
    }

    /**
     * Draft → Validated. Runs the manifest / payload / dependency / compatibility
     * validation steps; nothing is auto-corrected (Rule 5).
     */
    public function validate(string $rid): PublishingResult
    {
        [$record, $manifest, $from] = $this->load($rid);

        if ($from === ResourceStatus::Validated) {
            return PublishingResult::ok(ResourceStatus::Validated);
        }

        $result = $this->pipeline->validate($manifest, $record['payload'], $record['preview']);
        if ($result->failed()) {
            return $result;
        }

        $this->lifecycle->assertTransition($from, ResourceStatus::Validated);
        $this->persist($record, ResourceStatus::Validated);

        return PublishingResult::ok(ResourceStatus::Validated);
    }

    /**
     * Validated → Packaged. Generates immutable package metadata (Rule 6).
     */
    public function package(string $rid): PublishingResult
    {
        [$record, $manifest, $from] = $this->load($rid);

        $this->lifecycle->assertTransition($from, ResourceStatus::Packaged);

        $result = $this->pipeline->run($manifest, $record['payload'], $record['preview']);
        if ($result->failed()) {
            return $result;
        }

        $this->persist($record, ResourceStatus::Packaged, [
            'pending_package' => $result->package?->toArray(),
        ]);

        return PublishingResult::ok(ResourceStatus::Packaged, $result->package);
    }

    /**
     * Packaged → Published. Enforces version monotonicity, records history and locks
     * the manifest (Rules 7, 8, 11, 14).
     */
    public function publish(string $rid): PublishingResult
    {
        [$record, $manifest, $from] = $this->load($rid);

        $this->lifecycle->assertTransition($from, ResourceStatus::Published);

        $history = $this->historyOf($record);
        $latest = $history->latestVersion();
        if ($latest !== null && version_compare($manifest->version, $latest, '<=')) {
            return PublishingResult::fail(
                PublishingResult::STEP_VERSION,
                ["version '{$manifest->version}' must be greater than the published version '{$latest}' (never overwrite)."],
            );
        }

        $result = $this->pipeline->run($manifest, $record['payload'], $record['preview']);
        if ($result->failed()) {
            return $result;
        }

        $package = $result->package;
        $history = $history->record($package);

        $this->persist($record, ResourceStatus::Published, [
            'history' => $history->toArray(),
            'package' => $package->toArray(),
            'pending_package' => null,
            'restore_to' => null,
        ]);

        return PublishingResult::ok(ResourceStatus::Published, $package);
    }

    /**
     * Published → Deprecated. Deprecated resources remain searchable/installable with
     * a warning; no automatic migration (Rule 9).
     */
    public function deprecate(string $rid): PublishingResult
    {
        [$record, , $from] = $this->load($rid);

        $this->lifecycle->assertTransition($from, ResourceStatus::Deprecated);
        $this->persist($record, ResourceStatus::Deprecated);

        return PublishingResult::ok(ResourceStatus::Deprecated);
    }

    /**
     * Published|Deprecated → Archived. Hidden by default, never deleted, restorable
     * (Rule 10); history is preserved.
     */
    public function archive(string $rid): PublishingResult
    {
        [$record, , $from] = $this->load($rid);

        $this->lifecycle->assertTransition($from, ResourceStatus::Archived);
        $this->persist($record, ResourceStatus::Archived, ['restore_to' => $from->value]);

        return PublishingResult::ok(ResourceStatus::Archived);
    }

    /**
     * Archived → previous status. Restores a hidden resource to where it was.
     */
    public function restore(string $rid): PublishingResult
    {
        [$record, , $from] = $this->load($rid);

        $target = ResourceStatus::tryFromString($record['lifecycle']['restore_to'] ?? null) ?? ResourceStatus::Published;
        $this->lifecycle->assertTransition($from, $target);
        $this->persist($record, $target, ['restore_to' => null]);

        return PublishingResult::ok($target);
    }

    public function status(string $rid): ResourceStatus
    {
        [, , $status] = $this->load($rid);

        return $status;
    }

    public function history(string $rid): PublishingHistory
    {
        [$record] = $this->load($rid);

        return $this->historyOf($record);
    }

    // ---- internals ----------------------------------------------------------

    /**
     * @return array{0: array<string, mixed>, 1: ResourceManifest, 2: ResourceStatus}
     */
    private function load(string $rid): array
    {
        $record = $this->store->find($rid);
        if ($record === null) {
            throw PublishingException::notFound($rid);
        }

        return [$record, $this->parser->fromArray($record['manifest']), $this->statusOf($record)];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function statusOf(array $record): ResourceStatus
    {
        // Absent lifecycle ⇒ externally provided (offline bundle / mirror) ⇒ Published
        // by convention, so such resources are visible without a workflow run.
        return ResourceStatus::tryFromString($record['lifecycle']['status'] ?? null) ?? ResourceStatus::Published;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function historyOf(array $record): PublishingHistory
    {
        return PublishingHistory::fromArray($record['lifecycle']['history'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $overrides
     */
    private function persist(array $record, ResourceStatus $status, array $overrides = []): void
    {
        $lifecycle = is_array($record['lifecycle'] ?? null) ? $record['lifecycle'] : [];
        $lifecycle['status'] = $status->value;
        $lifecycle += ['restore_to' => null, 'history' => [], 'package' => null, 'pending_package' => null];

        foreach ($overrides as $key => $value) {
            $lifecycle[$key] = $value;
        }

        $this->store->writeLifecycle((string) $record['slug'], $lifecycle);
    }
}
