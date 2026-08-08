<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Publishing;

use Plugins\PageBuilder\Community\Publishing\Exceptions\PublishingException;

/**
 * The Community Resource lifecycle state machine (Phase 26.50B, Rule 1).
 *
 * It owns the ONLY legal transitions between {@see ResourceStatus} values. The
 * canonical forward path is Draft → Validated → Packaged → Published → Deprecated →
 * Archived; a Published or Deprecated resource may be Archived, an Archived resource
 * may be restored, and a Deprecated resource may be reinstated. Every other
 * transition is rejected. It is pure logic — no persistence, no I/O — reused
 * identically by Community, Marketplace, Enterprise and PRO (Rule 15).
 *
 * @see docs/resource-lifecycle.md
 */
final class ResourceLifecycle
{
    public function initial(): ResourceStatus
    {
        return ResourceStatus::Draft;
    }

    /**
     * The allowed target statuses for each source status.
     *
     * @return array<string, array<int, ResourceStatus>>
     */
    public function transitions(): array
    {
        return [
            ResourceStatus::Draft->value => [ResourceStatus::Validated],
            ResourceStatus::Validated->value => [ResourceStatus::Packaged, ResourceStatus::Draft],
            ResourceStatus::Packaged->value => [ResourceStatus::Published, ResourceStatus::Draft],
            ResourceStatus::Published->value => [ResourceStatus::Deprecated, ResourceStatus::Archived],
            ResourceStatus::Deprecated->value => [ResourceStatus::Archived, ResourceStatus::Published],
            ResourceStatus::Archived->value => [ResourceStatus::Published, ResourceStatus::Deprecated],
        ];
    }

    public function canTransition(ResourceStatus $from, ResourceStatus $to): bool
    {
        foreach ($this->transitions()[$from->value] ?? [] as $allowed) {
            if ($allowed === $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws PublishingException when the transition is not permitted
     */
    public function assertTransition(ResourceStatus $from, ResourceStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw PublishingException::illegalTransition($from, $to);
        }
    }
}
