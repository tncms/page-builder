<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

use Plugins\PageBuilder\LayoutTranslation\Contracts\LayoutOwnerLocatorInterface;
use Plugins\PageBuilder\LayoutTranslation\State\LayoutTranslationExistence;

/**
 * Fail-closed integrity validation of a resolved {@see LayoutTranslationDescriptor}
 * — LT-1 (locked).
 *
 * It validates only what requires context BEYOND the value objects: relationship
 * validity (carried by the resolver as a typed fault) and integrity against the
 * outside world (owner existence, storage soundness). It does NOT re-check
 * structural invariants — identity coherence and cross-dimension state
 * consistency are enforced at value-object construction and cannot be violated —
 * and it does NOT check capability against status, which is an Eligibility concern
 * outside LT-1. Deterministic, read-only; it never mutates anything.
 */
final class LayoutRelationshipValidator
{
    public function __construct(private readonly LayoutOwnerLocatorInterface $owners) {}

    public function validate(LayoutTranslationDescriptor $descriptor): RelationshipValidationReport
    {
        // An unsupported owner type is not a translatable relationship at all —
        // nothing else can be trusted.
        if ($descriptor->fault() === LayoutTranslationResolutionFault::UnsupportedOwnerType) {
            return new RelationshipValidationReport($descriptor, [RelationshipValidationIssue::UnsupportedOwnerType]);
        }

        $issues = [];

        if ($descriptor->fault() === LayoutTranslationResolutionFault::LocaleNotEnabled) {
            $issues[] = RelationshipValidationIssue::LocaleNotEnabled;
        }

        if ($descriptor->existence() === LayoutTranslationExistence::InvalidReference) {
            $issues[] = RelationshipValidationIssue::InvalidLayoutReference;
        }

        if ($descriptor->hasLayout() && ! $this->owners->exists($descriptor->ownerType(), $descriptor->ownerId())) {
            $issues[] = RelationshipValidationIssue::OrphanLayout;
        }

        return new RelationshipValidationReport($descriptor, $issues);
    }
}
