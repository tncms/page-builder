<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

use Plugins\PageBuilder\Package\DesignPackageManifest;

/**
 * The immutable recovery plan — always produced BEFORE any execution (LT-9D, per INV-LT-9D-02).
 *
 * Layer: Translation (recovery / plan). It states the reason, the eligibility, the chosen strategy, the
 * expected impact, the required permissions, the potential conflicts, and the deterministic decision. It is
 * the artifact a user confirms; there is no execution path that does not first produce one. Its
 * {@see fingerprint()} is a canonical, content-free hash of the plan — identical inputs produce an identical
 * fingerprint (INV-LT-9D-05), and execution validates against it.
 *
 * The expected impact is always honest about the append-only model: recovery may create a NEW execution, a
 * NEW history row, a NEW baseline and a NEW timeline entry, but it NEVER modifies history, snapshots or the
 * baseline chain, and it NEVER replaces an execution (INV-LT-9D-01).
 */
final class RecoveryPlan
{
    /**
     * @param  array<string, bool>  $expectedImpact
     * @param  array<int, string>  $requiredPermissions
     * @param  array<int, RecoveryConflict>  $conflicts
     */
    private function __construct(
        public readonly int $entryId,
        public readonly string $pageTranslationKey,
        public readonly RecoveryReason $reason,
        public readonly RecoveryEligibility $eligibility,
        public readonly RecoveryMode $strategy,
        public readonly array $expectedImpact,
        public readonly array $requiredPermissions,
        public readonly array $conflicts,
        public readonly RecoveryDecision $decision,
    ) {}

    /**
     * @param  array<int, RecoveryConflict>  $conflicts
     */
    public static function build(
        RecoveryRequest $request,
        RecoveryCandidate $candidate,
        RecoveryEligibility $eligibility,
        array $conflicts,
        RecoveryDecision $decision,
    ): self {
        return new self(
            $candidate->entryId->value,
            $request->target->value(),
            $request->reason,
            $eligibility,
            $request->mode,
            self::impactFor($request->mode),
            self::permissionsFor($request->mode, $decision),
            array_values($conflicts),
            $decision,
        );
    }

    /**
     * @return array<string, bool>
     */
    private static function impactFor(RecoveryMode $mode): array
    {
        $executing = $mode->isExecuting();

        return [
            'creates_new_execution' => $executing,
            'creates_new_history' => $executing,
            'creates_new_baseline' => $executing,
            'creates_new_timeline_entry' => $executing,
            // The append-only invariants — never true for any mode (INV-LT-9D-01).
            'modifies_history' => false,
            'rewrites_snapshots' => false,
            'modifies_baseline_history' => false,
            'deletes_timeline_entries' => false,
            'replaces_execution' => false,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function permissionsFor(RecoveryMode $mode, RecoveryDecision $decision): array
    {
        $permissions = ['timeline.recovery.view', 'timeline.recovery.plan'];

        if ($mode->executePermission() !== null) {
            $permissions[] = $mode->executePermission();
        }
        if ($decision === RecoveryDecision::RequiresOverride) {
            $permissions[] = 'timeline.recovery.override';
        }

        return $permissions;
    }

    public function decision(): RecoveryDecision
    {
        return $this->decision;
    }

    /** Executing recovery always requires explicit confirmation before delegation (INV-LT-9D-02). */
    public function requiresConfirmation(): bool
    {
        return $this->strategy->isExecuting();
    }

    /**
     * A canonical, content-free fingerprint of the plan. Deterministic: identical inputs → identical hash.
     */
    public function fingerprint(): string
    {
        return hash('sha256', DesignPackageManifest::canonicalJson($this->canonical()));
    }

    /**
     * @return array<string, mixed>
     */
    private function canonical(): array
    {
        return [
            'entry_id' => $this->entryId,
            'page_translation_key' => $this->pageTranslationKey,
            'reason' => $this->reason->value,
            'strategy' => $this->strategy->value,
            'eligibility' => $this->eligibility->toArray(),
            'expected_impact' => $this->expectedImpact,
            'required_permissions' => $this->requiredPermissions,
            'conflicts' => array_map(static fn (RecoveryConflict $c): array => $c->toArray(), $this->conflicts),
            'decision' => $this->decision->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->canonical() + ['fingerprint' => $this->fingerprint()];
    }
}
