<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

/**
 * The aggregated compliance report for one component provider (PB-XREG-1).
 *
 * A provider is compliant only when EVERY check passes. The public registration
 * gate ({@see ComponentRegistration}) refuses a provider whose report does not
 * pass, so a non-compliant provider can never reach the component registry.
 */
final class ComponentComplianceReport
{
    /**
     * @param  array<int, ComponentComplianceCheck>  $checks
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

    /**
     * @return array<int, ComponentComplianceCheck>
     */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (ComponentComplianceCheck $c): bool => ! $c->passed,
        ));
    }

    /** A single-line summary suitable for exception messages / assertion output. */
    public function summary(): string
    {
        if ($this->passed()) {
            return "Component provider '{$this->namespace}' is compliant (".count($this->checks).' checks).';
        }

        $lines = array_map(
            static fn (ComponentComplianceCheck $c): string => "{$c->name}: {$c->message}",
            $this->failures(),
        );

        return "Component provider '{$this->namespace}' failed compliance — ".implode('; ', $lines);
    }

    /**
     * @return array{provider: string, passed: bool, checks: array<int, array{name: string, passed: bool, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->namespace,
            'passed' => $this->passed(),
            'checks' => array_map(static fn (ComponentComplianceCheck $c): array => [
                'name' => $c->name,
                'passed' => $c->passed,
                'message' => $c->message,
            ], $this->checks),
        ];
    }
}
