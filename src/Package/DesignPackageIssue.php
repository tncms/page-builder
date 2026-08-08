<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Package;

/**
 * A single package diagnostic. Blockers prevent import; warnings do not.
 * `code` is an invariant KEY; `message` is human-facing text.
 */
final class DesignPackageIssue
{
    public const BLOCKER = 'blocker';
    public const WARNING = 'warning';

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly string $severity,
        public readonly array $context = [],
    ) {
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function blocker(string $code, string $message, array $context = []): self
    {
        return new self($code, $message, self::BLOCKER, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $code, string $message, array $context = []): self
    {
        return new self($code, $message, self::WARNING, $context);
    }

    public function isBlocker(): bool
    {
        return $this->severity === self::BLOCKER;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'severity' => $this->severity,
            'context' => $this->context,
        ];
    }
}
