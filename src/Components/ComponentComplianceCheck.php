<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

/**
 * The result of a single component-provider compliance check (PB-XREG-1).
 *
 * Immutable. Names one contract obligation (namespace, identity, components,
 * identifiers, kinds, duplicates, renderer, determinism, metadata, isolation) and
 * records whether the provider satisfied it, with a human-readable message on
 * failure. Deliberately mirrors the Resource Provider SDK's ComplianceCheck so the
 * two developer surfaces feel identical, without sharing a type.
 */
final class ComponentComplianceCheck
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
