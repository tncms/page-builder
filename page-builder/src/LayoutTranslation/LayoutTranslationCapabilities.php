<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

/**
 * The operations a layout translation supports (LT-1).
 *
 * Future Builder implementations advertise capabilities here instead of callers
 * branching on concrete classes or feature phases. In LT-1 — a foundation with no
 * translation operation implemented — every capability is unsupported
 * ({@see self::none()}); later phases return richer sets as clone / sync / diff /
 * review land.
 */
final class LayoutTranslationCapabilities
{
    public function __construct(
        private readonly bool $clone,
        private readonly bool $sync,
        private readonly bool $diff,
        private readonly bool $review,
        private readonly bool $preview,
        private readonly bool $publish,
    ) {}

    /** No capability — the LT-1 foundation default. */
    public static function none(): self
    {
        return new self(false, false, false, false, false, false);
    }

    public function supportsClone(): bool
    {
        return $this->clone;
    }

    public function supportsSync(): bool
    {
        return $this->sync;
    }

    public function supportsDiff(): bool
    {
        return $this->diff;
    }

    public function supportsReview(): bool
    {
        return $this->review;
    }

    public function supportsPreview(): bool
    {
        return $this->preview;
    }

    public function supportsPublish(): bool
    {
        return $this->publish;
    }

    /**
     * @return array<string, bool>
     */
    public function toArray(): array
    {
        return [
            'clone' => $this->clone,
            'sync' => $this->sync,
            'diff' => $this->diff,
            'review' => $this->review,
            'preview' => $this->preview,
            'publish' => $this->publish,
        ];
    }
}
