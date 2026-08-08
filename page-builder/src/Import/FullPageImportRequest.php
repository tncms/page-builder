<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Import;

/**
 * An immutable import request. The server resolves the resource from the RID via
 * the repository — it never trusts a client-supplied builder payload.
 *
 * `expectedPlanHash` / `expectedRevision` come from a prior dry run and let the
 * commit verify that (a) the confirmed plan is the one being applied and (b) the
 * target draft has not changed since (optimistic concurrency). Both are optional
 * for programmatic callers.
 */
final class FullPageImportRequest
{
    public function __construct(
        public readonly string $rid,
        public readonly string $ownerType,
        public readonly int|string $ownerId,
        public readonly ?string $expectedPlanHash = null,
        public readonly ?string $expectedRevision = null,
        public readonly ?string $actorId = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input, ?string $actorId = null): self
    {
        return new self(
            rid: (string) ($input['rid'] ?? ''),
            ownerType: (string) ($input['ownerType'] ?? $input['owner_type'] ?? ''),
            ownerId: $input['ownerId'] ?? $input['owner_id'] ?? '',
            expectedPlanHash: isset($input['planHash']) ? (string) $input['planHash'] : (isset($input['plan_hash']) ? (string) $input['plan_hash'] : null),
            expectedRevision: isset($input['revision']) ? (string) $input['revision'] : null,
            actorId: $actorId,
        );
    }
}
