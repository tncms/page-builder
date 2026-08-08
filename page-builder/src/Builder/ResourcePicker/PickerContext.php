<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\ResourcePicker;

use Plugins\PageBuilder\Repository\Rid;

/**
 * The Builder's editing context, which decides WHICH Design Resource kind the
 * picker may offer (Phase 26.47B, Rule 2 — "context determines resource kinds").
 *
 * Contexts map to a single canonical resource kind:
 *
 *   - Page editor      → page
 *   - Insert Section   → section
 *   - Insert Block     → block
 *   - (Insert Kit      → kit — reserved, not enabled)
 *
 * A full-page context also carries the owner document (ownerType/ownerId) because
 * the page import writes into that document's Builder draft. Section/block
 * contexts carry no owner: they return a resource to the Builder, which owns the
 * mutation. This object never touches Builder state, the CommandBus, or the
 * repository — it only describes intent.
 */
final class PickerContext
{
    /** The resource kinds a Builder context may pick. Kit is reserved (not enabled). */
    public const SUPPORTED_KINDS = [Rid::KIND_PAGE, Rid::KIND_SECTION, Rid::KIND_BLOCK];

    private function __construct(
        public readonly string $kind,
        public readonly ?string $ownerType = null,
        public readonly int|string|null $ownerId = null,
    ) {
    }

    public static function page(string $ownerType, int|string $ownerId): self
    {
        return new self(Rid::KIND_PAGE, $ownerType, $ownerId);
    }

    public static function section(): self
    {
        return new self(Rid::KIND_SECTION);
    }

    public static function block(): self
    {
        return new self(Rid::KIND_BLOCK);
    }

    /**
     * Build a context from a kind and optional owner, or null when the kind is not
     * a Builder-pickable kind.
     */
    public static function tryMake(string $kind, ?string $ownerType = null, int|string|null $ownerId = null): ?self
    {
        if (! in_array($kind, self::SUPPORTED_KINDS, true)) {
            return null;
        }

        return new self($kind, $ownerType, $ownerId);
    }

    /**
     * The resource kinds the picker exposes. A Builder context is single-kind by
     * design so unrelated kinds are never shown (Rule 2).
     *
     * @return array<int, string>
     */
    public function allowedKinds(): array
    {
        return [$this->kind];
    }

    public function isFullPage(): bool
    {
        return $this->kind === Rid::KIND_PAGE;
    }

    public function hasOwner(): bool
    {
        return is_string($this->ownerType)
            && $this->ownerType !== ''
            && $this->ownerId !== null
            && (string) $this->ownerId !== '';
    }

    /**
     * The primary card action label for this context: full pages use a template,
     * everything else is inserted (Phase 26.47D — "Import" is reserved for package
     * ingestion only).
     */
    public function actionLabel(): string
    {
        return match ($this->kind) {
            Rid::KIND_PAGE => 'Use Template',
            Rid::KIND_SECTION => 'Insert Section',
            Rid::KIND_BLOCK => 'Insert Block',
            default => 'Insert',
        };
    }
}
