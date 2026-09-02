<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Provenance;

/**
 * The authorization context an attestation is bound to (PB-TREG-2, Model A).
 *
 * A materialization attestation is NOT single-use — a stateless HMAC cannot be
 * consumed without an atomic server nonce store. Instead it is bounded replay-safe:
 * the same token may legitimately bless identical subtrees only within ONE authorized
 * window, defined by the authenticated editor, the layout owner being edited, and the
 * draft version-token baseline present at materialization. Outside that window (another
 * user, another owner/layout, or after any persisted save advances the token) the token
 * no longer verifies, so replay is bounded and truthful ("another copy derived from the
 * same registry resource within the same authorized edit").
 */
final class AttestationContext
{
    public function __construct(
        public readonly string $userId,
        public readonly string $ownerKey,
        public readonly string $versionToken,
    ) {
    }

    /** Compose the stable owner identity used in the binding. */
    public static function owner(string $ownerType, int|string $ownerId): string
    {
        return $ownerType.':'.$ownerId;
    }

    /**
     * The canonical, ordered binding map embedded in (and checked against) the token.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'uid' => $this->userId,
            'own' => $this->ownerKey,
            'tok' => $this->versionToken,
        ];
    }
}
