<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\SDK;

/**
 * The result of a single compliance check against a provider (Phase 26.49B, Rule 3).
 *
 * Immutable. A check names one contract obligation (key, priority, revision,
 * search, find, all, availability, metadata, isolation) and records whether the
 * provider satisfied it, with a human-readable message on failure.
 */
final class ComplianceCheck
{
    public function __construct(
        public readonly string $name,
        public readonly bool $passed,
        public readonly string $message = '',
    ) {}

    public static function pass(string $name): self
    {
        return new self($name, true);
    }

    public static function fail(string $name, string $message): self
    {
        return new self($name, false, $message);
    }
}
