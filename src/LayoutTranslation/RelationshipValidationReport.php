<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

/**
 * The immutable outcome of {@see LayoutRelationshipValidator::validate()} — LT-1.
 *
 * Fail-closed: any issue means the relationship is invalid. An empty report passes.
 * It is keyed to the {@see LayoutTranslationDescriptor} it validated.
 */
final class RelationshipValidationReport
{
    /**
     * @param  array<int, RelationshipValidationIssue>  $issues
     */
    public function __construct(
        public readonly LayoutTranslationDescriptor $descriptor,
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
     * @return array<int, RelationshipValidationIssue>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    public function has(RelationshipValidationIssue $issue): bool
    {
        return in_array($issue, $this->issues, true);
    }

    /**
     * @return array<int, string>
     */
    public function issueCodes(): array
    {
        return array_map(static fn (RelationshipValidationIssue $i): string => $i->value, $this->issues);
    }
}
