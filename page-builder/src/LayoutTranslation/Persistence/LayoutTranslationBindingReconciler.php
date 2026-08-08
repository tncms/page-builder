<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence;

use Plugins\PageBuilder\LayoutTranslation\DefaultLayoutTranslationStateReader;
use Plugins\PageBuilder\LayoutTranslation\Identity\LayoutKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingRepositoryInterface;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\BindingReconciliationReport;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\BindingWriteOutcome;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\PersistedLayoutTranslationBinding;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationExistence;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use Plugins\PageBuilder\Support\EditableOwners;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;
use Throwable;

/**
 * Additive, deterministic, fail-closed backfill for existing layouts — LT-2.
 *
 * Layer: Persistence (application-level reconciliation, run explicitly — never in
 * the migration). For each pb_layouts row it creates a DEFAULT-locale binding only
 * when every canonical identity resolves through existing public contracts and the
 * owning content exists. It never fabricates identity (no group from locale, no
 * page_id as a parallel group id), never overwrites an existing binding (idempotent
 * skip), and reports records it could not resolve rather than guessing.
 */
final class LayoutTranslationBindingReconciler
{
    public function __construct(
        private readonly LayoutTranslationBindingRepositoryInterface $repository,
        private readonly LanguageConfigurationContract $languages,
        private readonly DefaultLayoutTranslationStateReader $documentReader,
    ) {}

    public function reconcile(): BindingReconciliationReport
    {
        $default = $this->languages->normalize($this->languages->defaultLocale());

        $scanned = $created = $skipped = $unresolved = $conflicts = 0;
        $unresolvedRecords = [];

        foreach (PageBuilderLayout::query()->get() as $row) {
            $scanned++;
            $ownerType = (string) $row->owner_type;
            $ownerId = $row->owner_id;

            // Fail closed: the owner must be a supported, existing Core Content.
            if (EditableOwners::resolve($ownerType, $ownerId) === null) {
                $unresolved++;
                $unresolvedRecords[] = ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'reason' => 'unresolvable_owner'];

                continue;
            }

            try {
                $group = TranslationGroupKey::forOwner($ownerType, $ownerId);
                $translation = PageTranslationKey::forGroup($group, $default);
                $layout = LayoutKey::forTranslation($translation);
            } catch (Throwable $e) {
                $unresolved++;
                $unresolvedRecords[] = ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'reason' => 'identity_error'];

                continue;
            }

            $status = $this->documentReader->statusFor($ownerType, $ownerId, $default, true);

            // No layout document to bind (row exists but carries no payload).
            if ($status->existence() === LayoutTranslationExistence::NotCreated) {
                $skipped++;

                continue;
            }

            $result = $this->repository->createBinding(
                new PersistedLayoutTranslationBinding($group, $translation, $layout, (int) $row->getKey(), $status),
            );

            match ($result->outcome()) {
                BindingWriteOutcome::Created => $created++,
                BindingWriteOutcome::AlreadyExists => $skipped++,
                BindingWriteOutcome::Conflict => $conflicts++,
                default => $unresolved++,
            };
        }

        return new BindingReconciliationReport($scanned, $created, $skipped, $unresolved, $conflicts, $unresolvedRecords);
    }
}
