<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Publishing;

/**
 * The immutable result of a publishing-pipeline or workflow step (Phase 26.50B,
 * Rules 3 & 5).
 *
 * On failure it names the exact pipeline step that failed (manifest / payload /
 * dependencies / compatibility / version) and carries every field error — nothing is
 * auto-corrected. On success it carries the resulting {@see ResourceStatus} and, for
 * a packaging/publish step, the generated {@see PackageMetadata}.
 */
final class PublishingResult
{
    public const STEP_MANIFEST = 'manifest';

    public const STEP_PAYLOAD = 'payload';

    public const STEP_DEPENDENCIES = 'dependencies';

    public const STEP_COMPATIBILITY = 'compatibility';

    public const STEP_VERSION = 'version';

    /**
     * @param  array<int, string>  $errors
     */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $step,
        public readonly array $errors,
        public readonly ?PackageMetadata $package,
        public readonly ?ResourceStatus $status,
    ) {}

    public static function ok(?ResourceStatus $status = null, ?PackageMetadata $package = null): self
    {
        return new self(true, null, [], $package, $status);
    }

    /**
     * @param  array<int, string>  $errors
     */
    public static function fail(string $step, array $errors): self
    {
        return new self(false, $step, array_values($errors), null, null);
    }

    public function failed(): bool
    {
        return ! $this->ok;
    }

    public function summary(): string
    {
        if ($this->ok) {
            return 'Publishing step succeeded'.($this->status !== null ? " ({$this->status->value})" : '').'.';
        }

        return "Publishing failed at step '{$this->step}' — ".implode('; ', $this->errors);
    }
}
