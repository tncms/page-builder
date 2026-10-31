<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Plugins\PageBuilder\Authority\ContentAuthority;
use Plugins\PageBuilder\Authority\PageBuilderAuthorityException;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationConfig;
use TheNguyen\CMS\ContentAuthority\ContentWriteContext;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;
use TheNguyen\CMS\Models\Content;
use TheNguyen\CMS\Services\ContentManager;

/**
 * Creates a missing locale's Core translation owner for the editor language
 * selector, then provisions its translated layout (PAGE_BUILDER_TRANSLATION_OWNER_CREATION_GAP).
 *
 * The Core `ContentTranslation` is created ONLY through Core's supported content
 * lifecycle ({@see ContentManager::update()} with the target `locale`), as this
 * extension, inside a Core content write unit
 * ({@see ContentAuthority::withContentUnit()}, PB-CORE37-R1): Core checks the
 * revision the editor was opened with, runs its owner guard and RBAC (edit,
 * edit-others, publish for a published page), bumps the revision once and keeps
 * slug generation, sanitization and the `cms.content.*` hooks. The new translation
 * starts from the source title; every other translatable field stays empty.
 *
 * Lock order follows Core: the canonical content row is locked FIRST, then the
 * translated layout rows are provisioned in the SAME transaction, so the Core
 * translation and the cloned layout either both exist afterwards or neither does.
 *
 * Idempotent: when the translation already exists (a repeated or concurrent click
 * that lost the race) it is provisioned and returned without a Core write, so no
 * revision is needed and nothing is duplicated or overwritten. Only a genuinely
 * new translation needs the expected revision; a stale one is a Core
 * ContentRevisionConflict with zero writes.
 *
 * Trade-off: Core's `cms.content.saving/saved` hooks and model events fire inside
 * this transaction, so on a rolled-back clone a non-transactional listener may
 * already have observed the translation write. Core defers its public cache flush
 * to after commit.
 *
 * Empty source (PB-CORE-AUTHORITY-REMEDIATION-R1, C): when the source language has
 * no Page Builder content to copy, creation is refused with the blank-layout
 * decision BEFORE any write, unless the request carries the user's explicit
 * {@see self::LAYOUT_BLANK} choice; then the translated layout is provisioned
 * blank ({@see BlankLayoutProvisioner}) in the same transaction.
 */
final class TranslationOwnerCreator
{
    /** The explicit "Create blank translation" choice. */
    public const LAYOUT_BLANK = 'blank';

    public function __construct(
        private readonly EffectiveEditorOwnerResolver $owners,
        private readonly FirstOpenLayoutProvisioner $provisioner,
        private readonly LayoutTranslationConfig $config,
        private readonly ContentManager $contents,
        private readonly LanguageConfigurationContract $languages,
        private readonly ContentAuthority $authority,
        private readonly BlankLayoutProvisioner $blank,
        private readonly SourceLayoutInspector $sources,
    ) {}

    /**
     * @param  int|null  $expectedRevision  the Core revision the editor was opened with
     * @param  string|null  $layoutChoice  null (clone the source) or {@see self::LAYOUT_BLANK}
     *
     * @throws EditorOwnerResolutionException
     * @throws PageBuilderAuthorityException
     * @throws \TheNguyen\CMS\ContentAuthority\ContentWriteException
     */
    public function create(string $ownerType, Content $owner, string $locale, ?Authenticatable $actor, ?int $expectedRevision = null, ?string $layoutChoice = null): TranslationOwnerCreation
    {
        // Dormant feature: every locale shares the source layout, so there is no
        // per-locale owner to create.
        if (! $this->config->enabled()) {
            throw EditorOwnerResolutionException::translationDisabled();
        }

        $this->authority->assertCanEdit($owner, $actor);
        $locale = $this->languages->normalize($locale);
        $actorId = $actor === null ? null : (string) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($ownerType, $owner, $locale, $actor, $actorId, $expectedRevision, $layoutChoice): TranslationOwnerCreation {
            // Serialize concurrent creates for the same page on the canonical Core row
            // (Core's lock order: content row first, PB rows after).
            $locked = Content::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
            $this->authority->assertNotManaged($locked);

            $existing = $this->existing($ownerType, $locked, $locale);
            if ($existing !== null) {
                $provision = $this->provision($existing, $layoutChoice, $actorId);

                return new TranslationOwnerCreation($existing, $provision, false, $this->authority->revisionOf($locked));
            }

            // A source with nothing to copy needs the explicit blank choice — decided
            // before the Core write, so the decision state writes nothing.
            if ($layoutChoice !== self::LAYOUT_BLANK) {
                $sourceLayout = $this->sources->classify(TranslationGroupKey::forOwner($ownerType, $locked->getKey()), $this->sourceLocale());
                if ($sourceLayout !== SourceLayoutInspector::CLONEABLE) {
                    throw EditorOwnerResolutionException::blankDecisionRequired($locale, $sourceLayout);
                }
            }

            /** @var Authenticatable $actor assertCanEdit() refused a guest */
            $this->authority->withContentUnit($locked, $expectedRevision, $actor, function (Content $unit) use ($locale, $actor): void {
                $this->contents->update($unit, [
                    'locale' => $locale,
                    'title' => $this->sourceTitle($unit),
                ], null, ContentWriteContext::extension($actor));
            }, recheck: ContentAuthority::CREATE_TRANSLATION, locale: $locale);

            $effective = $this->owners->resolve($ownerType, $locked->getKey(), $locale);
            $this->assertTranslationLocale($effective);
            $provision = $this->provision($effective, $layoutChoice, $actorId);

            return new TranslationOwnerCreation($effective, $provision, true, $this->authority->revisionOf($locked));
        });
    }

    /**
     * The already-existing translation owner, or null when the locale has no Core
     * translation yet. Any other resolution fault (disabled/unknown locale,
     * unsupported owner, source locale) stays a fail-closed rejection.
     */
    private function existing(string $ownerType, Content $owner, string $locale): ?EffectiveLayoutOwner
    {
        try {
            $effective = $this->owners->resolve($ownerType, $owner->getKey(), $locale);
        } catch (EditorOwnerResolutionException $e) {
            if ($e->reason !== 'translation_not_found') {
                throw $e;
            }

            return null;
        }

        $this->assertTranslationLocale($effective);

        return $effective;
    }

    private function provision(EffectiveLayoutOwner $effective, ?string $layoutChoice, ?string $actorId): LayoutProvisionResult
    {
        return $layoutChoice === self::LAYOUT_BLANK
            ? $this->blank->ensureBlank($effective, $actorId)
            : $this->provisioner->ensure($effective, $actorId);
    }

    private function assertTranslationLocale(EffectiveLayoutOwner $effective): void
    {
        if ($effective->isSourceLocale) {
            throw EditorOwnerResolutionException::sourceLocaleNotCreatable($effective->locale);
        }
    }

    private function sourceTitle(Content $owner): string
    {
        return (string) $owner->translations()->where('locale', $this->sourceLocale())->value('title');
    }

    private function sourceLocale(): string
    {
        return $this->languages->normalize($this->languages->defaultLocale());
    }
}
