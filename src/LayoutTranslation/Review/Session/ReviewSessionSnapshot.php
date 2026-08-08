<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Review\Session;

use Plugins\PageBuilder\LayoutTranslation\Execution\Provenance\PlanProvenance;

/**
 * A payload-safe, reconstruction-sufficient snapshot of a review session (LT-8B, per
 * LT-8A-REVIEW-WORKSPACE-ARCHITECTURE §8.3 and INV-LT-8A-07).
 *
 * Layer: Translation (review session / value). It carries ONLY what is needed to reconstruct a session
 * (INV-LT-8A-07): the id, the target key, the {@see PlanProvenance} fingerprints, the DecisionSet
 * snapshot (origins + enums + touched flags), and the non-authoritative selected origin. It never carries
 * the plan payload, node content, or a preview fragment — the plan is re-derived on resume and provenance
 * re-verified.
 */
final class ReviewSessionSnapshot
{
    /**
     * @param  array<string, array{decision:string, touched:bool}>  $decisions
     */
    public function __construct(
        public readonly string $id,
        public readonly string $targetKey,
        public readonly PlanProvenance $provenance,
        public readonly array $decisions,
        public readonly ?string $selectedOrigin,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'target_key' => $this->targetKey,
            'provenance' => $this->provenance->toArray(),
            'decisions' => $this->decisions,
            'selected_origin' => $this->selectedOrigin,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, array{decision:string, touched:bool}> $decisions */
        $decisions = is_array($data['decisions'] ?? null) ? $data['decisions'] : [];

        return new self(
            (string) ($data['id'] ?? ''),
            (string) ($data['target_key'] ?? ''),
            PlanProvenance::fromArray(is_array($data['provenance'] ?? null) ? $data['provenance'] : []),
            $decisions,
            isset($data['selected_origin']) ? (string) $data['selected_origin'] : null,
        );
    }
}
