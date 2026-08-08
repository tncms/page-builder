<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Results;

/**
 * The immutable outcome of binding integrity validation — LT-2.
 *
 * Layer: Persistence / Result. Fail-closed: any issue means the binding is not
 * trustworthy. Keyed to the persisted binding it validated.
 */
final class BindingIntegrityReport
{
    /**
     * @param  array<int, BindingIntegrityIssue>  $issues
     */
    public function __construct(
        public readonly PersistedLayoutTranslationBinding $binding,
        private readonly array $issues,
    ) {}

    public function passes(): bool
    {
        return $this->issues === [];
    }

    public function fails(): bool
    {
        return ! $this->passes();
    }

    /**
     * @return array<int, BindingIntegrityIssue>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    public function has(BindingIntegrityIssue $issue): bool
    {
        return in_array($issue, $this->issues, true);
    }

    /**
     * @return array<int, string>
     */
    public function issueCodes(): array
    {
        return array_map(static fn (BindingIntegrityIssue $i): string => $i->value, $this->issues);
    }
}
