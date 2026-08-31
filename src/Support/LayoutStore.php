<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Plugins\PageBuilder\Models\PageBuilderLayout;

/**
 * The layout persistence gateway (Phase 2 — Layout Storage).
 *
 * All reads/writes of stored layouts go through here so the rest of the plugin
 * never touches the Eloquent model directly. Layouts are keyed by their
 * polymorphic owner (owner type + id), one row per owner, with draft and
 * published payloads held side by side — publishing copies draft → published.
 *
 * Payloads are always structured arrays (sections → blocks), never HTML.
 */
class LayoutStore
{
    /**
     * Find the stored layout for an owner, or null when none exists yet.
     */
    public function find(string $ownerType, int|string $ownerId): ?PageBuilderLayout
    {
        return PageBuilderLayout::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->first();
    }

    /**
     * The persisted layout for an owner, or a fresh unsaved instance keyed to it.
     */
    public function resolve(string $ownerType, int|string $ownerId): PageBuilderLayout
    {
        return $this->find($ownerType, $ownerId)
            ?? new PageBuilderLayout([
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
            ]);
    }

    /**
     * The draft payload for an owner, or null when none is drafted.
     *
     * @return array<string, mixed>|null
     */
    public function getDraftPayload(string $ownerType, int|string $ownerId): ?array
    {
        return $this->find($ownerType, $ownerId)?->draft_payload;
    }

    /**
     * The published (live) payload for an owner, or null when none is published.
     *
     * @return array<string, mixed>|null
     */
    public function getPublishedPayload(string $ownerType, int|string $ownerId): ?array
    {
        return $this->find($ownerType, $ownerId)?->published_payload;
    }

    /**
     * Store (or replace) the draft payload for an owner. Creates the row on first
     * write. Does not touch the published payload or the editor mode.
     *
     * When $expectedToken is provided (PB-PUBLISH-R1, INV-PUB-06) the write is an
     * optimistic compare-and-swap: the caller's server-issued token is checked against
     * the current persisted state inside a locked transaction, and a mismatch throws
     * {@see LayoutVersionConflictException} with ZERO mutation, so a stale editor can
     * never overwrite a newer draft. A null token opts out (server-internal callers),
     * preserving the legacy single-actor semantics unchanged.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws LayoutVersionConflictException on a stale token.
     */
    public function saveDraft(
        string $ownerType,
        int|string $ownerId,
        array $payload,
        ?string $userId = null,
        ?string $expectedToken = null,
    ): PageBuilderLayout {
        return DB::transaction(function () use ($ownerType, $ownerId, $payload, $userId, $expectedToken): PageBuilderLayout {
            $layout = $this->lockedResolve($ownerType, $ownerId);
            $this->assertToken($layout, $expectedToken);

            $layout->draft_payload = $payload;
            $layout->draft_updated_by = $userId;
            $layout->save();

            return $layout;
        });
    }

    /**
     * Publish an owner's current draft: copy draft_payload → published_payload.
     * A no-op-safe operation — publishing with no draft publishes null. This is the
     * copy-only persistence primitive (render mode untouched); the editor publish path
     * uses {@see self::publishAsBuilder()} for the atomic, token-checked variant.
     */
    public function publish(
        string $ownerType,
        int|string $ownerId,
        ?string $userId = null,
    ): PageBuilderLayout {
        $layout = $this->resolve($ownerType, $ownerId);

        $layout->published_payload = $layout->draft_payload;
        $layout->published_by = $userId;
        $layout->save();

        return $layout;
    }

    /**
     * Publish from the editor: set builder render mode and copy draft → published in ONE
     * token-checked, row-locked transaction (PB-PUBLISH-R1, INV-PUB-06). Publishing makes
     * the owner render with the builder on the public frontend (Phase 14.3), so mode and
     * payload advance together — a stale $expectedToken throws
     * {@see LayoutVersionConflictException} before any write, so a stale Publish can never
     * slip between a mode set and the copy. A null token opts out (server-internal use).
     *
     * @throws LayoutVersionConflictException on a stale token.
     */
    public function publishAsBuilder(
        string $ownerType,
        int|string $ownerId,
        ?string $userId = null,
        ?string $expectedToken = null,
    ): PageBuilderLayout {
        return DB::transaction(function () use ($ownerType, $ownerId, $userId, $expectedToken): PageBuilderLayout {
            $layout = $this->lockedResolve($ownerType, $ownerId);
            $this->assertToken($layout, $expectedToken);

            $layout->editor_mode = PageBuilderLayout::MODE_BUILDER;
            $layout->published_payload = $layout->draft_payload;
            $layout->published_by = $userId;
            $layout->save();

            return $layout;
        });
    }

    /**
     * The current server-issued authoritative version token for an owner (INV-PUB-06).
     * Owners with no stored layout row resolve to the canonical empty-state token. Used
     * by the HTTP boundary to reject a tokenless write with a reconcilable current token.
     */
    public function currentToken(string $ownerType, int|string $ownerId): string
    {
        return $this->find($ownerType, $ownerId)?->versionToken() ?? PageBuilderLayout::emptyToken();
    }

    /**
     * Resolve the persisted layout for an owner under a row lock (or a fresh unsaved
     * instance keyed to it), so the concurrency check and the mutation that follows
     * share one serialized view of the row.
     */
    private function lockedResolve(string $ownerType, int|string $ownerId): PageBuilderLayout
    {
        return PageBuilderLayout::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->lockForUpdate()
            ->first()
            ?? new PageBuilderLayout([
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
            ]);
    }

    /**
     * Fail closed on a stale optimistic-concurrency token (INV-PUB-06). A null expected
     * token opts out entirely (single-actor / server-internal callers). Otherwise the
     * caller's token must equal the owner's current authoritative token, or the mutation
     * is abandoned via {@see LayoutVersionConflictException}.
     */
    private function assertToken(PageBuilderLayout $layout, ?string $expectedToken): void
    {
        if ($expectedToken === null) {
            return;
        }

        $current = $layout->versionToken();

        if (! hash_equals($current, $expectedToken)) {
            throw new LayoutVersionConflictException($current, [
                'token' => $current,
                'editor_mode' => $layout->editor_mode ?? PageBuilderLayout::MODE_CLASSIC,
                'has_draft' => $layout->draft_payload !== null,
                'has_published' => $layout->published_payload !== null,
            ]);
        }
    }

    /**
     * Set the editor mode ("classic" or "builder") for an owner. Creates the row
     * on first write. Stamps the actor as the last draft editor when provided.
     */
    public function setEditorMode(
        string $ownerType,
        int|string $ownerId,
        string $mode,
        ?string $userId = null,
    ): PageBuilderLayout {
        if (! in_array($mode, [PageBuilderLayout::MODE_CLASSIC, PageBuilderLayout::MODE_BUILDER], true)) {
            throw new InvalidArgumentException("Invalid editor mode [{$mode}].");
        }

        $layout = $this->resolve($ownerType, $ownerId);

        $layout->editor_mode = $mode;

        if ($userId !== null) {
            $layout->draft_updated_by = $userId;
        }

        $layout->save();

        return $layout;
    }

    /**
     * Whether an owner is set to render with the Page Builder. Owners with no
     * stored layout default to classic mode (false).
     */
    public function isPageBuilderMode(string $ownerType, int|string $ownerId): bool
    {
        return (bool) $this->find($ownerType, $ownerId)?->isPageBuilderMode();
    }
}
