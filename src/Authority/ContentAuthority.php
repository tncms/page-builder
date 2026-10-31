<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Authority;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use TheNguyen\CMS\ContentAuthority\ContentActionContext;
use TheNguyen\CMS\ContentAuthority\ContentActionReason;
use TheNguyen\CMS\ContentAuthority\ContentWriteContext;
use TheNguyen\CMS\Models\Content;
use TheNguyen\CMS\Models\ContentTranslation;
use TheNguyen\CMS\Services\ContentManager;
use TheNguyen\CMS\Services\PermissionManager;

/**
 * Page Builder's single consumer of the Core content-authority contracts
 * (PB-CORE37-R1; Core 1.0.0-beta.7.1.37, `docs/content-authority/02-PUBLIC-CONTRACT.md`).
 *
 * Publication (layout publish, render-mode change of a published layout) and
 * translation creation are decided by Core's content action contract
 * (`cms.content.actions`, CORE-CONTENT-ACTION-AUTHORIZATION-R1): PB only adds its
 * own, stricter capabilities (page-builder.edit, strict page-builder.publish) and
 * asks Core again on the LOCKED row inside {@see withContentUnit()}. Without that
 * contract (a Core release that does not ship it) these operations fail closed.
 *
 * Private draft writes (PB tables only, no public output) are Core's `edit` decision
 * with `ContentActionContext::privateDraft()` (CORE-CONTENT-AUTHORIZATION-R2, resolves
 * extension gap G-1) plus PB's own `page-builder.edit`. `privateDraft()` is never used
 * for publication or translation creation.
 *
 * Page Builder requires Core 1.0.0-beta.7.1.39, the first release that ships both
 * decisions ({@see \Plugins\PageBuilder\Support\CoreCompatibility}). PB keeps no
 * copy of Core's edit / ownership rules: without the contract every draft write fails
 * closed like publication (`core_authority_unavailable`).
 *
 * Writes that ARE Core writes (translation creation) go through ContentManager, so
 * Core's own gate stays the canonical enforcement there.
 *
 * Extension-managed content (`cms_contents.managed_by` set) is refused for every PB
 * mutation regardless of the actor's permissions: another extension owns the body.
 * Page Builder never claims, detaches or recovers content (Core owns recovery).
 *
 * Lock order (Core contract): {@see withContentUnit()} locks the Core content row
 * first; PB rows are locked inside the callback.
 */
final class ContentAuthority
{
    private const PREFIXES = ['post' => 'posts', 'page' => 'pages'];

    /**
     * Core `ContentAction` values. Passed as strings (the contract accepts
     * `ContentAction|string`) so PB never loads a Core class a Core release without
     * the contract does not have: there the operations fail closed with a 403.
     */
    public const EDIT = 'edit';

    public const EDIT_TRANSLATION = 'edit_translation';

    public const PUBLISH = 'publish';

    public const UNPUBLISH = 'unpublish';

    public const CREATE_TRANSLATION = 'create_translation';

    public function __construct(
        private readonly PermissionManager $permissions,
        private readonly ContentManager $contents,
    ) {}

    /**
     * The canonical Core content behind a layout owner: a `page`/`post` id, or a
     * `content_translation` id (its parent content). Null for anything else.
     */
    public static function canonicalFor(string $ownerType, int|string $ownerId): ?Content
    {
        if (isset(self::PREFIXES[$ownerType])) {
            return Content::query()->where('type', $ownerType)->whereKey($ownerId)->first();
        }

        if ($ownerType === 'content_translation') {
            $contentId = ContentTranslation::query()->whereKey($ownerId)->value('content_id');

            return $contentId === null ? null : Content::query()->whereKey($contentId)->first();
        }

        return null;
    }

    /** The extension that manages this content, or null for ordinary content. */
    public static function managedOwner(Content $content): ?string
    {
        $owner = $content->getAttribute('managed_by');

        return is_string($owner) && $owner !== '' ? $owner : null;
    }

    /**
     * Draft-level authority: open the editor, save drafts, insert resources and apply
     * templates to the draft of this content. $locale names an existing translation
     * whose private draft is edited (Core `edit_translation`); null is the content
     * itself (Core `edit`).
     *
     * @throws PageBuilderAuthorityException
     */
    public function assertCanEdit(Content $content, ?Authenticatable $actor, ?string $locale = null): void
    {
        $actor = $this->actor($actor);
        $this->prefix($content);
        $this->assertNotManaged($content);

        if (! $this->permissions->userCan($actor, 'page-builder.edit')) {
            throw PageBuilderAuthorityException::forbidden('unauthorized_builder_edit', 'You are not allowed to edit pages in Page Builder.');
        }

        $locale === null
            ? $this->assertCoreAllows($content, $actor, self::EDIT, privateDraft: true)
            : $this->assertCoreAllows($content, $actor, self::EDIT_TRANSLATION, $locale, privateDraft: true);
    }

    /**
     * Publication authority: publish a layout or change which source (classic /
     * builder) renders publicly. Core's `publish` decision (edit, ownership, managed,
     * strict publish) plus PB's own capabilities, strictly (never fail-open).
     *
     * @throws PageBuilderAuthorityException
     */
    public function assertCanPublish(Content $content, ?Authenticatable $actor): void
    {
        $actor = $this->actor($actor);
        $this->prefix($content);
        $this->assertNotManaged($content);

        if (! $this->permissions->userCan($actor, 'page-builder.edit')) {
            throw PageBuilderAuthorityException::forbidden('unauthorized_builder_edit', 'You are not allowed to edit pages in Page Builder.');
        }

        if (! $this->permissions->userCanStrict($actor, 'page-builder.publish')) {
            throw PageBuilderAuthorityException::forbidden('unauthorized_builder_publish', 'You are not allowed to publish Page Builder layouts.');
        }

        $this->assertCoreAllows($content, $actor, self::PUBLISH);
    }

    /**
     * Ask Core's content action contract; a refusal becomes a bounded PB 403/404.
     * PB never widens a Core decision. $privateDraft (edit only) asks Core whether
     * the actor may change PB's private draft, which has no public output.
     *
     * @throws PageBuilderAuthorityException
     */
    public function assertCoreAllows(Content $content, ?Authenticatable $actor, string $action, ?string $locale = null, bool $privateDraft = false): void
    {
        $actor = $this->actor($actor);
        $publication = in_array($action, [self::PUBLISH, self::UNPUBLISH], true);
        $message = $publication
            ? 'You are not allowed to publish, unpublish or change published content.'
            : 'You are not allowed to edit this content.';

        if (! app()->bound('cms.content.actions')) {
            throw PageBuilderAuthorityException::forbidden('core_authority_unavailable', $message);
        }

        $context = $locale === null ? ContentActionContext::none() : ContentActionContext::forLocale($locale);
        $decision = app('cms.content.actions')->decision(
            $actor,
            $action,
            $content,
            $privateDraft ? $context->privateDraft() : $context,
        );

        if ($decision->allowed()) {
            return;
        }

        throw match ($decision->reason) {
            ContentActionReason::Unauthenticated => PageBuilderAuthorityException::unauthenticated(),
            ContentActionReason::UnsupportedSubject, ContentActionReason::UnsupportedAction => PageBuilderAuthorityException::unsupportedType((string) $content->getAttribute('type')),
            ContentActionReason::OwnershipDenied => PageBuilderAuthorityException::forbidden('unauthorized_edit_others', 'You are not allowed to edit content written by someone else.'),
            // A private draft is never refused for being published; only a trashed subject is.
            ContentActionReason::StatusDenied => $privateDraft
                ? PageBuilderAuthorityException::forbidden('unauthorized_edit', $message)
                : PageBuilderAuthorityException::forbidden('unauthorized_publish', 'You are not allowed to publish, unpublish or change published content.'),
            ContentActionReason::LocaleDenied => PageBuilderAuthorityException::forbidden('unauthorized_locale', 'You are not allowed to edit this content.'),
            default => PageBuilderAuthorityException::forbidden($publication ? 'unauthorized_publish' : 'unauthorized_edit', $message),
        };
    }

    public function canEdit(Content $content, ?Authenticatable $actor): bool
    {
        return $this->allows(fn () => $this->assertCanEdit($content, $actor));
    }

    public function canPublish(Content $content, ?Authenticatable $actor): bool
    {
        return $this->allows(fn () => $this->assertCanPublish($content, $actor));
    }

    /** Whether the actor may create the missing $locale translation (offer it in the UI). */
    public function canCreateTranslation(Content $content, ?Authenticatable $actor, string $locale): bool
    {
        return $this->allows(function () use ($content, $actor, $locale): void {
            $this->assertCanEdit($content, $actor);
            $this->assertCoreAllows($content, $actor, self::CREATE_TRANSLATION, $locale);
        });
    }

    /** The committed Core revision of the content (0 for a missing row). */
    public function revisionOf(Content|int $content): int
    {
        return $this->contents->revisionOf($content);
    }

    /**
     * Run a PB write as one unit with the Core content: the Core row is locked and
     * the caller's expected Core revision checked first (stale → Core
     * ContentRevisionConflict, zero writes), the managed marker is re-read on the
     * LOCKED row and, when $recheck is given, that action's authority is asked again
     * on the locked row (publish: {@see assertCanPublish()}; any other action: Core's
     * decision), then $callback runs inside the same transaction. Nested Core writes
     * in the callback go through Core's gate as this extension; a throw anywhere rolls
     * every write back.
     *
     * @template T
     *
     * @param  Closure(Content): T  $callback
     * @return T
     *
     * @throws PageBuilderAuthorityException
     * @throws \TheNguyen\CMS\ContentAuthority\ContentWriteException
     */
    public function withContentUnit(Content $content, ?int $expectedRevision, Authenticatable $actor, Closure $callback, ?string $recheck = null, ?string $locale = null): mixed
    {
        if ($expectedRevision === null) {
            throw PageBuilderAuthorityException::revisionRequired($this->revisionOf($content));
        }

        return $this->contents->transaction(
            $content,
            $expectedRevision,
            ContentWriteContext::extension($actor),
            function (Content $locked) use ($callback, $actor, $recheck, $locale): mixed {
                $this->assertNotManaged($locked);

                match ($recheck) {
                    null => null,
                    self::PUBLISH => $this->assertCanPublish($locked, $actor),
                    default => $this->assertCoreAllows($locked, $actor, $recheck, $locale),
                };

                return $callback($locked);
            },
        );
    }

    /** @throws PageBuilderAuthorityException */
    public function assertNotManaged(Content $content): void
    {
        $owner = self::managedOwner($content);

        if ($owner !== null) {
            throw PageBuilderAuthorityException::managed($owner);
        }
    }

    private function prefix(Content $content): string
    {
        $type = (string) $content->getAttribute('type');

        return self::PREFIXES[$type] ?? throw PageBuilderAuthorityException::unsupportedType($type);
    }

    private function actor(?Authenticatable $actor): Authenticatable
    {
        return $actor ?? throw PageBuilderAuthorityException::unauthenticated();
    }

    private function allows(Closure $check): bool
    {
        try {
            $check();

            return true;
        } catch (PageBuilderAuthorityException) {
            return false;
        }
    }
}
