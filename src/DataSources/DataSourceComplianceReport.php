<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

/**
 * The aggregated compliance report for one data-source provider (PB-XREG-2).
 * A provider is compliant only when EVERY check passes.
 */
final class DataSourceComplianceReport
{
    /**
     * @param  array<int, DataSourceComplianceCheck>  $checks
     */
    public function __construct(
        public readonly string $namespace,
        public readonly array $checks,
    ) {}

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if (! $check->passed) {
                return false;
            }
        }

        return true;
    }

    /** @return array<int, DataSourceComplianceCheck> */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (DataSourceComplianceCheck $c): bool => ! $c->passed,
        ));
    }

    public function summary(): string
    {
        if ($this->passed()) {
            return "Data-source provider '{$this->namespace}' is compliant (".count($this->checks).' checks).';
        }

        $lines = array_map(
            static fn (DataSourceComplianceCheck $c): string => "{$c->name}: {$c->message}",
            $this->failures(),
        );

        return "Data-source provider '{$this->namespace}' failed compliance — ".implode('; ', $lines);
    }

    /**
     * @return array{provider: string, passed: bool, checks: array<int, array{name: string, passed: bool, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->namespace,
            'passed' => $this->passed(),
            'checks' => array_map(static fn (DataSourceComplianceCheck $c): array => [
                'name' => $c->name,
                'passed' => $c->passed,
                'message' => $c->message,
            ], $this->checks),
        ];
    }
}
