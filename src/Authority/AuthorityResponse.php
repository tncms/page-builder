<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Authority;

use Illuminate\Http\JsonResponse;
use TheNguyen\CMS\ContentAuthority\ContentRevisionConflict;
use TheNguyen\CMS\ContentAuthority\ContentWriteDenied;
use Throwable;

/**
 * Maps authority / Core-revision refusals to the documented JSON envelope
 * (PB-CORE37-R1). Returns null for anything else so unexpected errors stay
 * ordinary logged failures — never a fake success.
 *
 * | Refusal                                   | Status | error                          |
 * |-------------------------------------------|--------|--------------------------------|
 * | Core ContentRevisionConflict (stale)      | 409    | pb_core_revision_conflict      |
 * | Core revision_required / PB missing rev   | 409    | pb_core_revision_required      |
 * | Core ContentWriteDenied (any other code)  | 403    | pb_core_{code}                 |
 * | PB authority refusal                      | 4xx    | pb_{reason}                    |
 *
 * 409 bodies carry `data.content_revision` (the current committed revision) for an
 * explicit reload — the client never adopts it silently.
 */
final class AuthorityResponse
{
    public static function from(Throwable $e, ?int $currentRevision = null): ?JsonResponse
    {
        return match (true) {
            $e instanceof ContentRevisionConflict => self::json(409, 'pb_core_revision_conflict', $e->getMessage(), [
                'content_revision' => $e->actual(),
            ]),
            $e instanceof ContentWriteDenied && $e->errorCode() === 'revision_required' => self::json(409, 'pb_core_revision_required', $e->getMessage(), [
                'content_revision' => $currentRevision,
            ]),
            $e instanceof ContentWriteDenied => self::json(403, 'pb_core_'.$e->errorCode(), $e->getMessage()),
            $e instanceof PageBuilderAuthorityException => self::json($e->statusCode, 'pb_'.$e->reason, $e->getMessage(), $e->current),
            default => null,
        };
    }

    /**
     * HTTP guard for the draft-only endpoints (import, template apply, provenance):
     * abort with the documented 403 JSON unless the actor holds draft authority on
     * the owner's canonical content. An unknown owner is left to the endpoint's own
     * not-found reporting.
     */
    public static function abortUnlessCanEditOwner(string $ownerType, int|string $ownerId): void
    {
        $content = ContentAuthority::canonicalFor($ownerType, $ownerId);

        if ($content === null) {
            return;
        }

        try {
            app(ContentAuthority::class)->assertCanEdit($content, auth()->user());
        } catch (PageBuilderAuthorityException $e) {
            /** @var JsonResponse $refusal */
            $refusal = self::from($e);
            abort($refusal);
        }
    }

    /** A localized, user-facing message for a refusal (Livewire notifications). */
    public static function message(Throwable $e): string
    {
        return self::translate($e->getMessage());
    }

    /** @param  array<string, mixed>  $data */
    private static function json(int $status, string $error, string $message, array $data = []): JsonResponse
    {
        $body = ['success' => false, 'error' => $error, 'message' => self::translate($message)];

        if ($data !== []) {
            $body['data'] = $data;
        }

        // A refusal describes the actor's rights on a specific record: never cacheable.
        return response()->json($body, $status)->header('Cache-Control', 'no-store, private');
    }

    private static function translate(string $message): string
    {
        $locale = self::adminLocale();

        // Core's refusal sentences live in the Core catalogue (tn_trans); PB's own in PB's.
        $translated = function_exists('tn_trans') ? tn_trans($message, [], $locale) : $message;

        return $translated !== $message ? $translated : __($message, [], $locale);
    }

    /**
     * The actor's admin UI locale. The PB API routes do not run the admin-locale
     * middleware, so a JSON refusal resolves it explicitly (Core's locale preference,
     * the same source the admin panel uses); null falls back to the app locale.
     */
    private static function adminLocale(): ?string
    {
        if (! app()->bound('cms.locale_preference') || ! app()->bound('request')) {
            return null;
        }

        try {
            return app('cms.locale_preference')->resolveAdminLocale(request(), auth()->user());
        } catch (\Throwable) {
            return null;
        }
    }
}
