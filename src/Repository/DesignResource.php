<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository;

use Plugins\PageBuilder\Contracts\DesignResourceContract;

/**
 * An immutable Design Resource — the single, canonical representation of a
 * reusable design artifact (block / section / page / kit).
 *
 * The object is read-only: identity (C1), classification (C2), descriptive (C3),
 * payload (C4), preview (C5), compatibility (C7), and distribution/licensing (C8)
 * are fixed at construction. C1–C7 describe *what the resource is* and are
 * identical across editions/channels; C8 (distribution) describes *how it is
 * distributed* and never influences rendering. It exposes no file paths or
 * package-internal layout.
 */
final class DesignResource implements DesignResourceContract
{
    /**
     * @param  array<string, mixed>  $payload       id-free, renderable LayoutPayload (block/section/page)
     * @param  array<int, string>    $members       RIDs (kit only)
     * @param  array<int, string>    $tags          invariant tag KEYS
     * @param  array<string, mixed>|null  $preview          {thumbnail?, previewUrl?, ...}
     * @param  array<string, mixed>|null  $compatibility    {schemaVersion, builder?, requiredComponents?, requiredFeatures?}
     * @param  array<string, mixed>       $distribution     {entitlement?, license?, ...} (C8, read-only, never affects runtime)
     * @param  array<string, mixed>|null  $package          {packageId?, source?} metadata only — NO paths
     * @param  array<string, array<string, string>>|null  $localized  optional per-field locale maps for the
     *          human presentation fields only ({name?: {locale: string}, description?: {locale: string}}). It is
     *          resolved by Admin UI locale at the catalog presentation boundary; it NEVER affects identity, the
     *          payload, or the invariant machine fields ($rid/$source/$kind/$resourceId/$version/$category/$tags),
     *          and is intentionally NOT emitted by {@see toMetadata()} (raw maps are never shipped).
     * @param  bool  $browsable  presentation-only: whether this resource is a curated, user-selectable design
     *          resource eligible for the unified Resource Browser catalog. Raw component substrate (generated from
     *          the section/block registries as an editor-palette fallback) sets this false so it never masquerades
     *          as a curated resource. It NEVER affects identity, the payload, RID materialization, or the editor
     *          component palette — only whether the resource is offered as a curated browse item.
     */
    public function __construct(
        public readonly string $rid,
        public readonly string $source,
        public readonly string $kind,
        public readonly string $resourceId,
        public readonly string $version,
        public readonly string $name,
        public readonly string $category,
        public readonly array $tags = [],
        public readonly array $payload = [],
        public readonly array $members = [],
        public readonly ?string $vendor = null,
        public readonly ?string $description = null,
        public readonly ?array $preview = null,
        public readonly ?array $compatibility = null,
        public readonly array $distribution = [],
        public readonly ?array $package = null,
        public readonly ?array $localized = null,
        public readonly bool $browsable = true,
    ) {
    }

    public function rid(): Rid
    {
        return new Rid($this->source, $this->kind, $this->resourceId);
    }

    public function isKit(): bool
    {
        return $this->kind === Rid::KIND_KIT;
    }

    public function entitlement(): string
    {
        $value = $this->distribution['entitlement'] ?? 'free';

        return is_string($value) ? $value : 'free';
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags, true);
    }

    /**
     * Metadata-only projection for library listings (no payload/tree).
     *
     * @return array<string, mixed>
     */
    public function toMetadata(): array
    {
        return [
            'rid' => $this->rid,
            'source' => $this->source,
            'kind' => $this->kind,
            'resourceId' => $this->resourceId,
            'version' => $this->version,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'tags' => $this->tags,
            'vendor' => $this->vendor,
            'preview' => $this->preview,
            'compatibility' => $this->compatibility,
            'entitlement' => $this->entitlement(),
            'package' => $this->package,
        ];
    }
}
