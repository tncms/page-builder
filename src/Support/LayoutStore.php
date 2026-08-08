<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

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
     * @param  array<string, mixed>  $payload
     */
    public function saveDraft(
        string $ownerType,
        int|string $ownerId,
        array $payload,
        ?string $userId = null,
    ): PageBuilderLayout {
        $layout = $this->resolve($ownerType, $ownerId);

        $layout->draft_payload = $payload;
        $layout->draft_updated_by = $userId;
        $layout->save();

        return $layout;
    }

    /**
     * Publish an owner's current draft: copy draft_payload → published_payload.
     * A no-op-safe operation — publishing with no draft publishes null.
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
