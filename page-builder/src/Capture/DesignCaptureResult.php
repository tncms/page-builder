<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Capture;

/**
 * The immutable outcome of a Design Capture (Phase 26.48).
 *
 *   - saved     — persisted to the repository (via the store); carries rid + fingerprint.
 *   - duplicate — an identical design already exists (same fingerprint); the caller
 *                 may Cancel or Create Copy (Update Existing is reserved).
 *   - invalid   — validation rejected the selection before persistence.
 */
final class DesignCaptureResult
{
    public const SAVED = 'saved';
    public const DUPLICATE = 'duplicate';
    public const INVALID = 'invalid';

    /**
     * @param  array<int, array{code: string, message: string}>  $issues
     */
    private function __construct(
        public readonly string $status,
        public readonly ?string $rid,
        public readonly ?string $fingerprint,
        public readonly ?string $existingRid,
        public readonly array $issues,
    ) {
    }

    public static function saved(string $rid, string $fingerprint): self
    {
        return new self(self::SAVED, $rid, $fingerprint, null, []);
    }

    public static function duplicate(string $fingerprint, string $existingRid): self
    {
        return new self(self::DUPLICATE, null, $fingerprint, $existingRid, []);
    }

    /**
     * @param  array<int, array{code: string, message: string}>  $issues
     */
    public static function invalid(array $issues): self
    {
        return new self(self::INVALID, null, null, null, array_values($issues));
    }

    public function ok(): bool
    {
        return $this->status === self::SAVED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'rid' => $this->rid,
            'fingerprint' => $this->fingerprint,
            'existingRid' => $this->existingRid,
            'issues' => $this->issues,
        ];
    }
}
