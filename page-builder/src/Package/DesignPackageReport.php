<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Package;

/**
 * The outcome of validating or importing a Design Package — a dry run and a real
 * import share the same shape (only {@see $imported} differs).
 */
final class DesignPackageReport
{
    /**
     * @param  array<int, string>  $rids            resource RIDs (imported:*) the package yields
     * @param  array<int, DesignPackageIssue>  $blockers
     * @param  array<int, DesignPackageIssue>  $warnings
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $packageName,
        public readonly int $resourceCount,
        public readonly array $rids,
        public readonly array $blockers,
        public readonly array $warnings,
        public readonly bool $imported = false,
    ) {
    }

    public function isImportable(): bool
    {
        return $this->blockers === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'packageName' => $this->packageName,
            'resourceCount' => $this->resourceCount,
            'rids' => $this->rids,
            'blockers' => array_map(static fn (DesignPackageIssue $i): array => $i->toArray(), $this->blockers),
            'warnings' => array_map(static fn (DesignPackageIssue $i): array => $i->toArray(), $this->warnings),
            'imported' => $this->imported,
        ];
    }
}
