<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\ReviewInfrastructure;

use Plugins\PageBuilder\LayoutTranslation\Baseline\BaselineFingerprint;
use Plugins\PageBuilder\LayoutTranslation\Diff\StructuralDiffEngine;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationStateLoaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Provenance\PlanProvenance;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Plan\SynchronizationPlanner;
use Plugins\PageBuilder\LayoutTranslation\Review\Contracts\ReviewInputProviderInterface;
use Plugins\PageBuilder\LayoutTranslation\Review\Subject\ReviewSubject;

/**
 * Production {@see ReviewInputProviderInterface}: builds the review subject by COMPOSING the frozen
 * pipeline (LT-8D).
 *
 * Layer: Translation (review infrastructure). It reads canonical state lock-free (LT-7 loader `load()`),
 * obtains the baseline document through the {@see BaselineDocumentSourceInterface} seam, and DELEGATES to
 * the frozen LT-5 {@see StructuralDiffEngine} and LT-6 {@see SynchronizationPlanner} — it computes no diff
 * or plan of its own and changes neither (INV-LT-8A-01). It captures the {@see PlanProvenance} exactly as
 * the coordinator will verify it (stored baseline fingerprint + fingerprint(S) + fingerprint(T)), so the
 * plan is provenance-consistent. Any inability to build a subject fails closed via
 * {@see ReviewSubjectUnavailableException}, which the presentation layer renders fail-soft.
 */
final class PlanningReviewInputProvider implements ReviewInputProviderInterface
{
    public function __construct(
        private readonly SynchronizationStateLoaderInterface $loader,
        private readonly BaselineDocumentSourceInterface $baselineDocuments,
        private readonly StructuralDiffEngine $diff,
        private readonly SynchronizationPlanner $planner,
    ) {}

    public function provide(PageTranslationKey $target): ReviewSubject
    {
        $state = $this->loader->load($target);

        if (! $state->isBound()) {
            throw ReviewSubjectUnavailableException::notReviewable('target_not_bound');
        }
        if (! $state->hasBaseline()) {
            throw ReviewSubjectUnavailableException::notReviewable('baseline_missing');
        }
        if (! $state->hasSource()) {
            throw ReviewSubjectUnavailableException::notReviewable('source_unavailable');
        }
        if (! $state->hasTarget()) {
            throw ReviewSubjectUnavailableException::notReviewable('target_unavailable');
        }

        $sourceDoc = $state->sourcePayload();
        $targetDoc = $state->targetPayload();

        $baselineDoc = $this->baselineDocuments->documentFor($target);
        if ($baselineDoc === null) {
            throw ReviewSubjectUnavailableException::baselineDocumentUnavailable();
        }

        // Provenance consistency: the baseline document must content-address to the STORED baseline
        // fingerprint, otherwise the diff would describe a baseline the pipeline never recorded.
        $storedFingerprint = (string) $state->baselineFingerprint();
        if (! hash_equals($storedFingerprint, BaselineFingerprint::of($baselineDoc))) {
            throw ReviewSubjectUnavailableException::baselineInconsistent();
        }

        // Frozen LT-5 diff → frozen LT-6 plan (consumed verbatim; never modified).
        $plan = $this->planner->plan($this->diff->threeWay($baselineDoc, $sourceDoc, $targetDoc));

        // Provenance captured exactly as the coordinator verifies it (INV-LT-7-EXEC-01).
        $provenance = new PlanProvenance(
            $storedFingerprint,
            BaselineFingerprint::of($sourceDoc),
            BaselineFingerprint::of($targetDoc),
        );

        return new ReviewSubject($target, $plan, $provenance);
    }
}
