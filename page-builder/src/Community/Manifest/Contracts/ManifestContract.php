<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest\Contracts;

/**
 * The official **Resource Manifest** interchange contract (Phase 26.50A, Rule 2).
 *
 * A manifest is the platform-neutral, self-describing envelope for one distributable
 * Design Resource. It is the ONE format every current and future channel shares —
 * Community today, and Marketplace / PRO / Enterprise / Private repositories later,
 * WITHOUT changing this contract (Rule 12). It carries identity, classification,
 * compatibility, dependency, license, asset, author and preview metadata — never a
 * Builder runtime, never proprietary or hidden fields (Rules 4, 13).
 *
 * A manifest is IMMUTABLE after publishing: a content change is a new version, i.e.
 * a new manifest with a new fingerprint (Rule 3). Implementations are readonly value
 * objects.
 *
 * @see docs/resource-manifest.md
 */
interface ManifestContract
{
    /** The manifest schema version (e.g. "1.0"). */
    public function schema(): string;

    /** The globally-unique Resource Identity `<source>:<kind>:<resourceId>`. */
    public function rid(): string;

    /** The deterministic content fingerprint (payload identity). */
    public function fingerprint(): string;

    /** The resource kind: section / block / page / kit. */
    public function resourceType(): string;

    /** The provider channel that owns this manifest (e.g. "community"). */
    public function provider(): string;

    /** The declared semantic version of this resource. */
    public function version(): string;

    /**
     * The canonical, platform-neutral array projection — the wire format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
