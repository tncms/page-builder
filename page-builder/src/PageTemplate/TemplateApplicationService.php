<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\PageTemplate;

use Illuminate\Support\Facades\DB;
use Plugins\PageBuilder\Import\FullPageImportIssue;
use Plugins\PageBuilder\Import\FullPageImportRequest;
use Plugins\PageBuilder\Import\FullPageImportService;
use Plugins\PageBuilder\PageTemplate\Exceptions\TemplateApplicationFailed;
use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\EditorUrls;
use TheNguyen\CMS\Services\ContentManager;

/**
 * Applies a Page Template to a destination Page/Post (Phase 26.47D, Part A).
 *
 * There are two destinations:
 *
 *   - Create New — create a Draft Page/Post, then apply the template to its
 *     Builder draft. Atomic: if the apply fails the draft content is rolled back
 *     so no half-applied page survives, and nothing is ever published.
 *   - Use Existing — apply the template to an existing Page/Post's Builder draft,
 *     with optimistic concurrency (planHash/revision) and the recovery snapshot,
 *     exactly as the Full Page import already provides.
 *
 * This service owns ONLY the create-draft + destination orchestration. The actual
 * page application always goes through the single, unchanged FullPageImportService
 * (draft only, transactional, snapshotted, rollback-safe). There is no second
 * importer and no second persistence model — content uses ContentManager, the
 * layout uses the Full Page import's LayoutStore path.
 */
final class TemplateApplicationService
{
    public function __construct(
        private readonly ContentManager $content,
        private readonly FullPageImportService $import,
        private readonly DesignResourceRepositoryInterface $repository,
    ) {
    }

    /**
     * Create a new Draft Page/Post and apply the template to its Builder draft.
     */
    public function createAndApply(
        string $rid,
        string $ownerType,
        string $title,
        ?string $locale,
        ?string $actorId,
    ): TemplateApplicationOutcome {
        $guard = $this->guardResource($rid, $ownerType);
        if ($guard !== null) {
            return $guard;
        }

        $title = trim($title) !== '' ? trim($title) : EditableOwners::label($ownerType);

        try {
            return DB::transaction(function () use ($rid, $ownerType, $title, $locale, $actorId): TemplateApplicationOutcome {
                $data = ['type' => $ownerType, 'title' => $title, 'status' => 'draft'];
                if ($locale !== null && $locale !== '') {
                    $data['locale'] = $locale;
                }

                $content = $this->content->create($data);

                $result = $this->import->import(new FullPageImportRequest(
                    rid: $rid,
                    ownerType: $ownerType,
                    ownerId: $content->getKey(),
                    actorId: $actorId,
                ));

                if (! $result->ok) {
                    // Roll back the just-created draft content — the destination
                    // must never be left as an empty/half-applied page.
                    throw new TemplateApplicationFailed($result->issues);
                }

                return TemplateApplicationOutcome::success(
                    $ownerType,
                    $content->getKey(),
                    $this->redirectUrl($ownerType, $content->getKey()),
                    created: true,
                );
            });
        } catch (TemplateApplicationFailed $e) {
            return TemplateApplicationOutcome::failure($this->issues($e->issues), $ownerType);
        }
    }

    /**
     * Apply the template to an existing Page/Post's Builder draft.
     */
    public function applyToExisting(
        string $rid,
        string $ownerType,
        int|string $ownerId,
        ?string $planHash,
        ?string $revision,
        ?string $actorId,
    ): TemplateApplicationOutcome {
        $guard = $this->guardResource($rid, $ownerType);
        if ($guard !== null) {
            return $guard;
        }

        if (EditableOwners::resolve($ownerType, $ownerId) === null) {
            return TemplateApplicationOutcome::reject(
                'destination_not_found',
                'The selected destination no longer exists.',
                $ownerType,
            );
        }

        $result = $this->import->import(new FullPageImportRequest(
            rid: $rid,
            ownerType: $ownerType,
            ownerId: $ownerId,
            expectedPlanHash: ($planHash !== null && $planHash !== '') ? $planHash : null,
            expectedRevision: ($revision !== null && $revision !== '') ? $revision : null,
            actorId: $actorId,
        ));

        if (! $result->ok) {
            return TemplateApplicationOutcome::failure($this->issues($result->issues), $ownerType, $ownerId);
        }

        return TemplateApplicationOutcome::success(
            $ownerType,
            $ownerId,
            $this->redirectUrl($ownerType, $ownerId),
            created: false,
        );
    }

    /**
     * Domain validation shared by both destinations: supported owner type, the
     * resource exists, and it is a full-page template. Entitlement (Phase 26.45)
     * is enforced by the controller before the service is reached.
     */
    private function guardResource(string $rid, string $ownerType): ?TemplateApplicationOutcome
    {
        if (! EditableOwners::isSupported($ownerType)) {
            return TemplateApplicationOutcome::reject(
                'unsupported_owner',
                'That destination type is not supported.',
                $ownerType,
            );
        }

        $resource = $this->repository->tryFind($rid);
        if ($resource === null) {
            return TemplateApplicationOutcome::reject('resource_not_found', 'The template could not be found.', $ownerType);
        }

        if ($resource->kind !== 'page') {
            return TemplateApplicationOutcome::reject(
                'wrong_kind',
                'Only full-page templates can be applied to a page or post.',
                $ownerType,
            );
        }

        return null;
    }

    private function redirectUrl(string $ownerType, int|string $ownerId): ?string
    {
        $structure = EditorUrls::structure($ownerType, (string) $ownerId);
        if ($structure !== null) {
            return $structure;
        }

        $owner = EditableOwners::resolve($ownerType, $ownerId);

        return $owner !== null ? EditableOwners::editUrl($ownerType, $owner) : null;
    }

    /**
     * @param  array<int, FullPageImportIssue>  $issues
     * @return array<int, array{code: string, message: string, severity: string}>
     */
    private function issues(array $issues): array
    {
        return array_map(static fn (FullPageImportIssue $issue): array => $issue->toArray(), $issues);
    }
}
