<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

/**
 * The result of a single data-source-provider compliance check (PB-XREG-2).
 * Mirrors the component/resource SDK check shape without sharing a type.
 */
final class DataSourceComplianceCheck
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
