<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence;

use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutOwnerLocatorInterface;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutDocumentLocatorInterface;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\BindingIntegrityIssue;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\BindingIntegrityReport;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Results\PersistedLayoutTranslationBinding;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationExistence;
use Plugins\PageBuilder\Support\EditableOwners;

/**
 * Fail-closed integrity validation of a persisted binding — LT-2.
 *
 * Layer: Persistence (validator). It detects integrity concerns that require
 * context BEYOND the value objects: an unsupported owner, an orphaned binding
 * (owning content gone), a missing referenced pb_layouts document, corrupt
 * persisted state, and canonical-key incoherence. It is deterministic and
 * read-only — it never persists, repairs, auto-creates or synchronizes. It is a
 * SEPARATE LT-2 validator and does not touch the frozen LT-1
 * LayoutRelationshipValidator or its responsibilities.
 */
final class LayoutTranslationBindingValidator
{
    public function __construct(
        private readonly LayoutOwnerLocatorInterface $owners,
        private readonly LayoutDocumentLocatorInterface $documents,
    ) {}

    public function validate(PersistedLayoutTranslationBinding $binding): BindingIntegrityReport
    {
        $issues = [];

        $ownerType = $binding->group()->ownerType;
        $ownerId = $binding->group()->ownerId;

        if (! EditableOwners::isSupported($ownerType)) {
            $issues[] = BindingIntegrityIssue::UnsupportedOwner;
        } elseif (! $this->owners->exists($ownerType, $ownerId)) {
            $issues[] = BindingIntegrityIssue::OrphanedBinding;
        }

        if (! $this->documents->exists($binding->layoutId())) {
            $issues[] = BindingIntegrityIssue::MissingReferencedLayout;
        }

        if ($binding->status()->existence() === LayoutTranslationExistence::InvalidReference) {
            $issues[] = BindingIntegrityIssue::UnreadablePersistedState;
        }

        if (! $this->identityCoheres($binding)) {
            $issues[] = BindingIntegrityIssue::CanonicalKeyMismatch;
        }

        return new BindingIntegrityReport($binding, $issues);
    }

    private function identityCoheres(PersistedLayoutTranslationBinding $binding): bool
    {
        return $binding->translation()->group()->equals($binding->group())
            && $binding->layout()->translation()->equals($binding->translation());
    }
}
