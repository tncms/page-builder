<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Recovery;

/**
 * The immutable, typed result of a recovery execution (LT-9D).
 *
 * Layer: Translation (recovery / result). Payload-safe — a status plus, on success, the NEW history id and
 * NEW baseline fingerprint the frozen Coordinator produced, and the source entry recovery was based on. It
 * never exposes node content, models, SQL, or raw exceptions. Recovery execution ALWAYS creates a new
 * execution; it never edits history (INV-LT-9D-01). Constructed only through named constructors.
 *
 *  - {@see created}  — the Coordinator committed a NEW execution (new history + new baseline + new entry).
 *  - {@see noop}     — the Coordinator determined there was nothing to apply; no new execution was created.
 *  - {@see rejected} — recovery validation refused delegation (e.g. unconfirmed, blocked, tampered plan).
 *  - {@see failed}   — the Coordinator refused or failed the new execution (typed detail).
 */
final class RecoveryExecutionResult
{
    public const STATUS_CREATED = 'created';

    public const STATUS_NOOP = 'noop';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    private function __construct(
        public readonly string $status,
        public readonly ?int $newHistoryId,
        public readonly ?string $newBaselineFingerprint,
        public readonly ?int $sourceEntryId,
        public readonly ?string $detail,
    ) {}

    public static function created(int $newHistoryId, string $newBaselineFingerprint, int $sourceEntryId): self
    {
        return new self(self::STATUS_CREATED, $newHistoryId, $newBaselineFingerprint, $sourceEntryId, null);
    }

    public static function noop(int $sourceEntryId): self
    {
        return new self(self::STATUS_NOOP, null, null, $sourceEntryId, null);
    }

    public static function rejected(string $detail): self
    {
        return new self(self::STATUS_REJECTED, null, null, null, $detail);
    }

    public static function failed(string $detail, ?int $sourceEntryId = null): self
    {
        return new self(self::STATUS_FAILED, null, null, $sourceEntryId, $detail);
    }

    /** A new append-only execution was created (never a mutation of history). */
    public function createdNewExecution(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }

    public function wasRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'new_history_id' => $this->newHistoryId,
            'new_baseline_fingerprint' => $this->newBaselineFingerprint,
            'source_entry_id' => $this->sourceEntryId,
            'detail' => $this->detail,
        ];
    }
}
