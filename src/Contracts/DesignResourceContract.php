<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Contracts;

use Plugins\PageBuilder\Repository\Rid;

/**
 * The official **Design Resource Contract** — the immutable projection of a
 * reusable design artifact (Phase 26.49A, Rules 2/3/4).
 *
 * FROZEN. A Design Resource is immutable after capture:
 *
 *   - RID (Resource Identity): globally unique, immutable, never regenerated,
 *     never reused, never editable. It identifies the RESOURCE, not its content.
 *   - Fingerprint (Content Identity): deterministic, canonical, payload-only.
 *     Two distinct resources (distinct RIDs) MAY share a fingerprint (duplicates).
 *   - Normalized payload: immutable — only ever replaced by the Capture Pipeline
 *     through a future Update Existing workflow (not implemented).
 *
 * This formalizes the pre-existing {@see \Plugins\PageBuilder\Repository\DesignResource}
 * accessor surface as a named contract; it introduces no new behaviour. The
 * immutable identity/classification/payload/preview fields are exposed as readonly
 * properties on the value object.
 *
 * @see docs/design-resource-contract.md
 */
interface DesignResourceContract
{
    /**
     * The structured Resource Identity (source + kind + resourceId).
     */
    public function rid(): Rid;

    public function isKit(): bool;

    /**
     * The distribution entitlement (C8) — never affects rendering.
     */
    public function entitlement(): string;

    public function hasTag(string $tag): bool;

    /**
     * Metadata-only projection for library listings (no payload/tree).
     *
     * @return array<string, mixed>
     */
    public function toMetadata(): array;
}
