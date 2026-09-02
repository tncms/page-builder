<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Provenance;

use RuntimeException;

/**
 * A bounded, namespaced provenance reconciliation failure (PB-TREG-2 schema subphase).
 *
 * Thrown when a save carries an invalid, forged, expired, moved, wrong-context,
 * duplicate, conflicting, or excessive materialization attestation. The whole save must
 * be rejected atomically. The message is a stable machine code only — it never carries
 * signing details, secrets, or raw token bytes.
 */
final class ProvenanceReconciliationException extends RuntimeException
{
    public const CODE_ATTESTATION_INVALID = 'attestation_invalid';
    public const CODE_ATTESTATION_UNMATCHED = 'attestation_unmatched';
    public const CODE_ATTESTATION_CONFLICT = 'attestation_conflict';
    public const CODE_ATTESTATION_LIMIT = 'attestation_limit';

    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
