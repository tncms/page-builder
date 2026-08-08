<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

/**
 * The result of validating a Resource Manifest (Phase 26.50A, Rule 15).
 *
 * Immutable. Collects one error per failed field-level check, keyed by the manifest
 * field it concerns, so a caller can reject a malformed manifest and report exactly
 * why.
 */
final class ManifestValidationResult
{
    /**
     * @param  array<int, array{field: string, message: string}>  $errors
     */
    public function __construct(
        public readonly array $errors,
    ) {}

    public function passed(): bool
    {
        return $this->errors === [];
    }

    public function failed(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<int, string>
     */
    public function fields(): array
    {
        return array_values(array_unique(array_map(static fn (array $e): string => $e['field'], $this->errors)));
    }

    public function summary(): string
    {
        if ($this->passed()) {
            return 'Manifest is valid.';
        }

        $lines = array_map(static fn (array $e): string => "{$e['field']}: {$e['message']}", $this->errors);

        return 'Invalid manifest — '.implode('; ', $lines);
    }

    /**
     * @return array<int, array{field: string, message: string}>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
