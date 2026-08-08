<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\SDK;

/**
 * The aggregated compliance report for one provider (Phase 26.49B, Rule 3).
 *
 * A provider is compliant only when EVERY check passes. Registration through
 * {@see ProviderRegistration} refuses to register a provider whose report does
 * not pass, so a non-compliant provider can never reach the Repository.
 */
final class ComplianceReport
{
    /**
     * @param  array<int, ComplianceCheck>  $checks
     */
    public function __construct(
        public readonly string $providerKey,
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
     * @return array<int, ComplianceCheck>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->checks, static fn (ComplianceCheck $c): bool => ! $c->passed));
    }

    /**
     * A single-line summary suitable for exception messages / assertion output.
     */
    public function summary(): string
    {
        if ($this->passed()) {
            return "Provider '{$this->providerKey}' is compliant (".count($this->checks).' checks).';
        }

        $lines = array_map(
            static fn (ComplianceCheck $c): string => "{$c->name}: {$c->message}",
            $this->failures(),
        );

        return "Provider '{$this->providerKey}' failed compliance — ".implode('; ', $lines);
    }

    /**
     * @return array{provider: string, passed: bool, checks: array<int, array{name: string, passed: bool, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->providerKey,
            'passed' => $this->passed(),
            'checks' => array_map(static fn (ComplianceCheck $c): array => [
                'name' => $c->name,
                'passed' => $c->passed,
                'message' => $c->message,
            ], $this->checks),
        ];
    }
}
