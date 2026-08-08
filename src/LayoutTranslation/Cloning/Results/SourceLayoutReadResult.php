<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Results;

/**
 * The immutable result of reading a source layout document (LT-3).
 *
 * Layer: Translation (clone result). Distinguishes "no source layout" from "source
 * layout exists but carries no valid/selectable payload", and carries the selected
 * payload + its origin (`published` | `draft`) on success. It returns a copy of
 * the payload array — never an Eloquent model.
 */
final class SourceLayoutReadResult
{
    private const FOUND = 'found';

    private const NOT_FOUND = 'not_found';

    private const PAYLOAD_UNAVAILABLE = 'payload_unavailable';

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function __construct(
        private readonly string $status,
        private readonly ?array $payload,
        private readonly ?string $origin,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  string  $origin  'published' | 'draft'
     */
    public static function found(array $payload, string $origin): self
    {
        return new self(self::FOUND, $payload, $origin);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND, null, null);
    }

    public static function payloadUnavailable(): self
    {
        return new self(self::PAYLOAD_UNAVAILABLE, null, null);
    }

    public function isFound(): bool
    {
        return $this->status === self::FOUND;
    }

    public function isNotFound(): bool
    {
        return $this->status === self::NOT_FOUND;
    }

    public function isPayloadUnavailable(): bool
    {
        return $this->status === self::PAYLOAD_UNAVAILABLE;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(): ?array
    {
        return $this->payload;
    }

    public function origin(): ?string
    {
        return $this->origin;
    }
}
