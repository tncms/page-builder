<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntryId;

/**
 * The immutable input for a recovery planning pass (LT-9D).
 *
 * Layer: Translation (recovery / command). It binds the target translation, the timeline entry being
 * recovered from, the requested {@see RecoveryMode} strategy, the {@see RecoveryReason} motivation, an
 * optional actor for audit, and the permissions granted to that actor. It carries no document content and
 * no execution authority — planning it produces a {@see RecoveryPlan}, never a mutation (INV-LT-9D-02).
 */
final class RecoveryRequest
{
    /**
     * @param  array<int, string>  $grantedPermissions
     */
    public function __construct(
        public readonly PageTranslationKey $target,
        public readonly TimelineEntryId $entryId,
        public readonly RecoveryMode $mode,
        public readonly RecoveryReason $reason,
        public readonly ?string $actor = null,
        public readonly array $grantedPermissions = [],
    ) {}

    public function grants(string $permission): bool
    {
        return in_array($permission, $this->grantedPermissions, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target' => $this->target->value(),
            'entry_id' => $this->entryId->value,
            'mode' => $this->mode->value,
            'reason' => $this->reason->value,
            'actor' => $this->actor,
            'granted_permissions' => array_values($this->grantedPermissions),
        ];
    }
}
