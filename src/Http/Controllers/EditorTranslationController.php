<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Http\Controllers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PageBuilder\Authority\AuthorityResponse;
use Plugins\PageBuilder\Authority\ContentAuthority;
use Plugins\PageBuilder\Authority\PageBuilderAuthorityException;
use Plugins\PageBuilder\Editor\Translation\BlankLayoutDecision;
use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Editor\Translation\TranslationOwnerCreator;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\EditorUrls;
use TheNguyen\CMS\ContentAuthority\ContentWriteException;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;

/**
 * Creates a missing locale's translation for the editor language selector
 * (PAGE_BUILDER_TRANSLATION_OWNER_CREATION_GAP).
 *
 * The URL addresses the CANONICAL page; the target locale is the only input. The
 * server creates the Core content translation through Core's content lifecycle and
 * provisions the translated layout atomically ({@see TranslationOwnerCreator}),
 * then returns the server-generated editor URL to open. Creating a Core translation
 * is a content write, so it requires BOTH `page-builder.edit` and the owner type's
 * Core edit permission; Core's gate then enforces ownership and publication for the
 * actual write (PB-CORE37-R1). The request carries the Core revision the editor was
 * opened with (`content_revision`); the response returns the committed revision.
 * Authority and revision refusals are handled 403/409 responses ({@see AuthorityResponse}),
 * never a 500. Responses are private / no-store.
 *
 * Empty source (PB-CORE-AUTHORITY-REMEDIATION-R1, C): when the source language has
 * no Page Builder content to copy, the request without a `layout` choice is a 409
 * `pb_blank_layout_decision_required` carrying the three choices and the Core
 * editor return URL, with nothing written. `layout=blank` is the explicit
 * "Create blank translation" choice; any other `layout` value is a 422.
 */
final class EditorTranslationController
{
    private const MAX_LOCALE_LENGTH = 16;

    public function __construct(
        private readonly TranslationOwnerCreator $creator,
    ) {}

    public function store(Request $request, string $ownerType, int|string $ownerId): JsonResponse
    {
        abort_unless(function_exists('cms_can') && cms_can('page-builder.edit'), 403);
        abort_unless(EditableOwners::isSupported($ownerType), 404);
        abort_unless(EditableOwners::canCreateTranslation($ownerType), 403);

        $owner = EditableOwners::resolve($ownerType, $ownerId);
        abort_if($owner === null, 404);

        try {
            $locale = $this->localeFrom($request);
            $layoutChoice = $this->layoutChoiceFrom($request);
            $result = $this->creator->create($ownerType, $owner, $locale, auth()->user(), $this->revisionFrom($request), $layoutChoice);
        } catch (PageBuilderAuthorityException|ContentWriteException $e) {
            /** @var JsonResponse $response both are mapped */
            $response = AuthorityResponse::from($e, app(ContentAuthority::class)->revisionOf($owner));

            return $response->header('Cache-Control', 'no-store, private');
        } catch (EditorOwnerResolutionException $e) {
            $data = BlankLayoutDecision::isDecision($e)
                ? BlankLayoutDecision::payload($e, $ownerType, $owner, $this->sourceLocale())
                : null;

            return $this->error($e->reason, $e->getMessage(), $e->statusCode, $data);
        } catch (UniqueConstraintViolationException) {
            // A concurrent create elsewhere claimed the same Core slug/translation
            // first; the transaction rolled back, so a retry is safe.
            return $this->error(
                'translation_conflict',
                'Another change created a conflicting translation at the same time. Please try again.',
                409,
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'locale' => $result->owner->locale,
                'created' => $result->created,
                'layout' => $result->provision->blank ? 'blank' : 'clone',
                'content_translation_id' => $result->owner->contentTranslationId,
                'editor_url' => EditorUrls::live($ownerType, $owner->getKey(), null, $result->owner->locale),
                'content_revision' => $result->contentRevision,
            ],
        ], $result->created ? 201 : 200)->header('Cache-Control', 'no-store, private');
    }

    /** @param array<string, mixed>|null $data structured detail the UI acts on */
    private function error(string $reason, string $message, int $status, ?array $data = null): JsonResponse
    {
        $body = ['success' => false, 'error' => $reason, 'message' => $message];
        if ($data !== null) {
            $body['data'] = $data;
        }

        return response()->json($body, $status)->header('Cache-Control', 'no-store, private');
    }

    private function sourceLocale(): string
    {
        $languages = app(LanguageConfigurationContract::class);

        return $languages->normalize($languages->defaultLocale());
    }

    /**
     * The explicit layout choice: absent (clone the source) or `blank`. Anything else
     * is refused before any write.
     */
    private function layoutChoiceFrom(Request $request): ?string
    {
        if (! $request->has('layout')) {
            return null;
        }

        $choice = $request->input('layout');
        if ($choice !== TranslationOwnerCreator::LAYOUT_BLANK) {
            throw EditorOwnerResolutionException::layoutChoiceInvalid();
        }

        return $choice;
    }

    /** The Core revision the editor captured when it opened, or null when absent. */
    private function revisionFrom(Request $request): ?int
    {
        $revision = $request->input('content_revision');

        return is_int($revision) || (is_string($revision) && ctype_digit($revision)) ? (int) $revision : null;
    }

    /**
     * The requested target locale. It is validated against the enabled locales by
     * the resolver; here it only has to be a short, non-empty string.
     */
    private function localeFrom(Request $request): string
    {
        $locale = $request->input('locale');
        $locale = is_string($locale) ? trim($locale) : '';

        if ($locale === '' || strlen($locale) > self::MAX_LOCALE_LENGTH) {
            throw EditorOwnerResolutionException::localeRequired();
        }

        return $locale;
    }
}
