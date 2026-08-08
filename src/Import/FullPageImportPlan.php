<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Import;

/**
 * The deterministic, read-only result of a dry run. It describes exactly what an
 * import would do without mutating anything.
 */
final class FullPageImportPlan
{
    /**
     * @param  array<string, mixed>              $compatibility
     * @param  array<int, array<string, mixed>>  $dependencies
     * @param  array<int, string>                $assets
     * @param  array<int, string>                $tokens
     * @param  array<int, FullPageImportIssue>   $warnings
     * @param  array<int, FullPageImportIssue>   $blockers
     */
    public function __construct(
        public readonly string $rid,
        public readonly string $version,
        public readonly string $kind,
        public readonly string $targetOwnerType,
        public readonly int|string $targetOwnerId,
        public readonly int $currentSectionCount,
        public readonly int $currentBlockCount,
        public readonly int $incomingSectionCount,
        public readonly int $incomingBlockCount,
        public readonly array $compatibility,
        public readonly array $dependencies,
        public readonly array $assets,
        public readonly array $tokens,
        public readonly array $warnings,
        public readonly array $blockers,
        public readonly string $planHash,
        public readonly string $revision,
    ) {
    }

    public function ok(): bool
    {
        return $this->blockers === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok(),
            'rid' => $this->rid,
            'version' => $this->version,
            'kind' => $this->kind,
            'target' => ['ownerType' => $this->targetOwnerType, 'ownerId' => $this->targetOwnerId],
            'counts' => [
                'currentSections' => $this->currentSectionCount,
                'currentBlocks' => $this->currentBlockCount,
                'incomingSections' => $this->incomingSectionCount,
                'incomingBlocks' => $this->incomingBlockCount,
            ],
            'compatibility' => $this->compatibility,
            'dependencies' => $this->dependencies,
            'assets' => $this->assets,
            'tokens' => $this->tokens,
            'warnings' => array_map(static fn (FullPageImportIssue $i): array => $i->toArray(), $this->warnings),
            'blockers' => array_map(static fn (FullPageImportIssue $i): array => $i->toArray(), $this->blockers),
            'planHash' => $this->planHash,
            'revision' => $this->revision,
        ];
    }
}
