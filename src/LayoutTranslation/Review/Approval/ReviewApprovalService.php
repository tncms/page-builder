<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Approval;

use Plugins\PageBuilder\LayoutTranslation\Execution\Commands\ExecuteSynchronizationCommand;
use Plugins\PageBuilder\LayoutTranslation\Execution\Contracts\SynchronizationCoordinatorInterface;
use Plugins\PageBuilder\LayoutTranslation\Execution\Results\SynchronizationExecutionOutcome;
use Plugins\PageBuilder\LayoutTranslation\Execution\Results\SynchronizationExecutionResult;
use Plugins\PageBuilder\LayoutTranslation\Review\Decision\DecisionSet;
use Plugins\PageBuilder\LayoutTranslation\Review\Session\ReviewSessionState;
use Plugins\PageBuilder\LayoutTranslation\Review\Subject\ReviewSubject;

/**
 * Assembles the execution command and hands it to the frozen coordinator (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §7).
 *
 * Layer: Translation (review approval). The single bridge from the Workspace to the frozen LT-7 pipeline.
 * It ASSEMBLES an {@see ExecuteSynchronizationCommand} from the bound subject (plan + provenance verbatim)
 * and the lowered decisions, invokes {@see SynchronizationCoordinatorInterface::execute}, and maps the
 * typed result to a terminal session state. It implements NONE of the pipeline (eligibility, loading,
 * provenance verify, mutation, persistence, history) — those stay LT-7 (INV-LT-8A-01/04). It never
 * re-plans and never touches content.
 */
final class ReviewApprovalService
{
    public function __construct(
        private readonly SynchronizationCoordinatorInterface $coordinator,
    ) {}

    public function submit(ReviewSubject $subject, DecisionSet $decisions, ?string $actor): SynchronizationExecutionResult
    {
        $command = new ExecuteSynchronizationCommand(
            $subject->target,
            $subject->plan,          // verbatim — never re-planned
            $subject->provenance,    // captured at open — verified once, under lock, by the coordinator
            $decisions->toAcceptance(),
            $actor,
        );

        return $this->coordinator->execute($command);
    }

    public static function terminalStateFor(SynchronizationExecutionResult $result): ReviewSessionState
    {
        return match ($result->outcome) {
            SynchronizationExecutionOutcome::Executed => ReviewSessionState::Executed,
            SynchronizationExecutionOutcome::NoOp => ReviewSessionState::NoOp,
            default => ReviewSessionState::Refused,
        };
    }
}
