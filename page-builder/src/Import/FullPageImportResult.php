<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Import;

/**
 * The outcome of a commit attempt. On failure `replaced` is false and the target
 * draft is unchanged.
 */
final class FullPageImportResult
{
    /**
     * @param  array<int, FullPageImportIssue>  $issues
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $rid,
        public readonly string $planHash,
        public readonly bool $replaced,
        public readonly ?FullPageImportSnapshot $snapshot,
        public readonly array $issues = [],
        public readonly int $resultSectionCount = 0,
        public readonly int $resultBlockCount = 0,
        public readonly ?string $revision = null,
    ) {
    }

    /**
     * @param  array<int, FullPageImportIssue>  $issues
     */
    public static function failed(string $rid, string $planHash, ?FullPageImportSnapshot $snapshot, array $issues): self
    {
        return new self(false, $rid, $planHash, false, $snapshot, $issues);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'rid' => $this->rid,
            'planHash' => $this->planHash,
            'replaced' => $this->replaced,
            'snapshot' => $this->snapshot?->toArray(),
            'issues' => array_map(static fn (FullPageImportIssue $i): array => $i->toArray(), $this->issues),
            'resultSectionCount' => $this->resultSectionCount,
            'resultBlockCount' => $this->resultBlockCount,
            'revision' => $this->revision,
        ];
    }
}
