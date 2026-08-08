<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Editor\Translation\EffectiveEditorOwnerResolver;
use Plugins\PageBuilder\Editor\Translation\EffectiveLayoutOwner;
use Plugins\PageBuilder\Editor\Translation\FirstOpenLayoutProvisioner;
use Plugins\PageBuilder\Editor\Translation\LayoutProvisionResult;
use Plugins\PageBuilder\Editor\Translation\LocaleSwitchProjector;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use TheNguyen\CMS\Models\Content;

/**
 * Assembles the editor bootstrap context for an owner (Phase 9).
 *
 * One place builds the {owner, layout, metadata, permissions} bundle the Vue
 * editor needs, so the Blade boot payload and the `/editor/context` API return
 * exactly the same shape. It reads through {@see LayoutStore} and the metadata
 * exporter — it never writes and never exposes raw owner model attributes.
 */
final class EditorContext
{
    public function __construct(
        private readonly LayoutStore $layouts,
        private readonly PageBuilderManager $manager,
        private readonly EffectiveEditorOwnerResolver $owners,
        private readonly FirstOpenLayoutProvisioner $provisioner,
        private readonly LocaleSwitchProjector $localeSwitch,
    ) {}

    /**
     * Resolve + first-open provision + assemble the editor context for a canonical
     * page in a requested locale (LT-M1B Increment 2).
     *
     * The single server-authoritative entry point the Filament editor boot uses:
     * it re-resolves the effective owner from the canonical page + locale through
     * the ONE resolver, provisions the translated layout on first open (idempotent)
     * and returns the same shape as {@see forResolvedOwner}. A forged/invalid locale
     * throws {@see \Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException}
     * — the caller (page mount) turns that into a fail-closed HTTP abort.
     *
     * @return array<string, mixed>
     */
    public function forCanonicalLocale(string $ownerType, Content $canonical, ?string $requestedLocale): array
    {
        $effective = $this->owners->resolve($ownerType, $canonical->getKey(), $requestedLocale);
        $provision = $this->provisioner->ensure($effective, $this->actorId());

        return $this->forResolvedOwner($ownerType, $canonical, $effective, $provision);
    }

    /**
     * Build the full context for a resolved owner Content record.
     *
     * @return array<string, mixed>
     */
    public function forOwner(string $ownerType, Content $owner): array
    {
        $ownerId = (string) $owner->getKey();
        $layout = $this->layouts->find($ownerType, $ownerId);

        $draft = $this->withIds($layout?->draft_payload);
        $published = $this->withIds($layout?->published_payload);
        $savedAt = $layout?->updated_at?->toIso8601String();

        return [
            'owner' => [
                'type' => $ownerType,
                'id' => $owner->getKey(),
                'label' => EditableOwners::label($ownerType),
                'title' => $owner->translatedTitle(),
                'edit_url' => EditableOwners::editUrl($ownerType, $owner),
            ],
            'layout' => [
                'editor_mode' => $layout?->editor_mode ?? PageBuilderLayout::MODE_CLASSIC,
                'draft_payload' => $draft,
                'published_payload' => $published,
                'has_draft' => $draft !== null,
                'has_published' => $published !== null,
                // Publish-truth signal (PB-STATE-2V): whether the published snapshot
                // already reflects the current draft. The editor derives its status
                // from THIS rather than the local save flag, so a saved-but-unpublished
                // draft can never read as a safe "Draft saved".
                'is_published_current' => $this->publishedIsCurrent($draft, $published),
                'last_saved_at' => $savedAt,
                'published_at' => $published !== null ? $savedAt : null,
            ],
            'metadata' => $this->manager->editorMetadata(),
            'permissions' => [
                'can_edit' => $this->can('page-builder.edit'),
                // Fall back to edit so a single-permission setup can still publish.
                'can_publish' => $this->can('page-builder.publish') || $this->can('page-builder.edit'),
            ],
        ];
    }

    /**
     * Build the editor context for a canonical page but load the layout from the
     * server-resolved effective owner (LT-M1B).
     *
     * The `owner` block stays the canonical page (title, edit URL); the `layout`
     * block is the locale-specific layout (source owner for the default locale, the
     * `content_translation` owner otherwise). A read-only `translation` block
     * exposes the resolved locale/owner/provisioning for diagnostics — the client
     * never computes the owner; the server re-resolves it on every mutation.
     *
     * @return array<string, mixed>
     */
    public function forResolvedOwner(
        string $ownerType,
        Content $canonical,
        EffectiveLayoutOwner $effective,
        LayoutProvisionResult $provision,
    ): array {
        $layout = $this->layouts->find($effective->ownerType, $effective->ownerId);

        $draft = $this->withIds($layout?->draft_payload);
        $published = $this->withIds($layout?->published_payload);
        $savedAt = $layout?->updated_at?->toIso8601String();

        return [
            'owner' => [
                'type' => $ownerType,
                'id' => $canonical->getKey(),
                'label' => EditableOwners::label($ownerType),
                'title' => $canonical->translatedTitle(),
                'edit_url' => EditableOwners::editUrl($ownerType, $canonical),
            ],
            'translation' => $effective->toArray() + [
                'layout_id' => $layout?->getKey(),
                'cloned' => $provision->cloned,
                'baseline_captured' => $provision->baselineCaptured,
                // Server-prepared switcher model (LT-M1B Inc2): enabled locales for
                // this translation group, each with its own server-generated editor
                // URL. The client never derives owner/layout/translation identity.
                'available_locales' => $this->localeSwitch->forOwner(
                    $ownerType,
                    $canonical->getKey(),
                    $effective->locale,
                ),
            ],
            'layout' => [
                'editor_mode' => $layout?->editor_mode ?? PageBuilderLayout::MODE_CLASSIC,
                'draft_payload' => $draft,
                'published_payload' => $published,
                'has_draft' => $draft !== null,
                'has_published' => $published !== null,
                // Publish-truth signal (PB-STATE-2V): whether the published snapshot
                // already reflects the current draft. The editor derives its status
                // from THIS rather than the local save flag, so a saved-but-unpublished
                // draft can never read as a safe "Draft saved".
                'is_published_current' => $this->publishedIsCurrent($draft, $published),
                'last_saved_at' => $savedAt,
                'published_at' => $published !== null ? $savedAt : null,
            ],
            'metadata' => $this->manager->editorMetadata(),
            'permissions' => [
                'can_edit' => $this->can('page-builder.edit'),
                'can_publish' => $this->can('page-builder.publish') || $this->can('page-builder.edit'),
            ],
        ];
    }

    /**
     * A concise status summary after a draft save / publish (endpoint payload).
     *
     * @return array<string, mixed>
     */
    public function summarize(PageBuilderLayout $layout): array
    {
        $draft = $this->withIds($layout->draft_payload);
        $published = $this->withIds($layout->published_payload);
        $savedAt = $layout->updated_at?->toIso8601String();

        return [
            'editor_mode' => $layout->editor_mode,
            'draft_payload' => $draft,
            'published_payload' => $published,
            'has_draft' => $draft !== null,
            'has_published' => $published !== null,
            'is_published_current' => $this->publishedIsCurrent($draft, $published),
            'last_saved_at' => $savedAt,
            'published_at' => $published !== null ? $savedAt : null,
        ];
    }

    /**
     * Normalize a stored payload and assign stable component ids (via the state
     * engine) so the editor canvas can address every section/block by id.
     * Returns null for a null/non-array payload.
     *
     * @param  mixed  $payload
     * @return array<string, mixed>|null
     */
    private function withIds(mixed $payload): ?array
    {
        return is_array($payload) ? $this->manager->state($payload)->payload() : null;
    }

    /**
     * Whether the published snapshot already reflects the current draft (PB-STATE-2V).
     *
     * Both operands are the SAME id-normalized payloads exposed to the editor, so a
     * byte-stable JSON comparison is an apples-to-apples publish-currency check. Two
     * nulls (nothing drafted, nothing published) compare equal — there is nothing to
     * publish, so the state is trivially current.
     *
     * @param  array<string, mixed>|null  $draft
     * @param  array<string, mixed>|null  $published
     */
    private function publishedIsCurrent(?array $draft, ?array $published): bool
    {
        return json_encode($draft, JSON_UNESCAPED_UNICODE)
            === json_encode($published, JSON_UNESCAPED_UNICODE);
    }

    private function can(string $permission): bool
    {
        return function_exists('cms_can') && cms_can($permission);
    }

    private function actorId(): ?string
    {
        $id = auth()->id();

        return $id === null ? null : (string) $id;
    }
}
