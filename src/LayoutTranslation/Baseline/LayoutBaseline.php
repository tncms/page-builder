<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Baseline;

use InvalidArgumentException;

/**
 * An immutable source baseline for a target page translation (LT-4).
 *
 * Layer: Translation (baseline / domain). The authoritative provenance record: the
 * content-addressed source fingerprint at baseline time, the source `pb_layouts.id`
 * it referenced, and when it was captured. Keyed by the canonical page-translation
 * key value. It carries no payload and no mutable state.
 */
final class LayoutBaseline
{
    public function __construct(
        public readonly string $pageTranslationKey,
        public readonly int $sourceLayoutId,
        public readonly string $fingerprint,
        public readonly string $capturedAt,
    ) {
        if (trim($pageTranslationKey) === '' || trim($fingerprint) === '' || trim($capturedAt) === '') {
            throw new InvalidArgumentException('A baseline requires a page translation key, fingerprint and captured-at.');
        }

        if ($sourceLayoutId <= 0) {
            throw new InvalidArgumentException('A baseline requires a positive source layout id.');
        }
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function matches(string $fingerprint): bool
    {
        return $this->fingerprint === $fingerprint;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page_translation_key' => $this->pageTranslationKey,
            'source_layout_id' => $this->sourceLayoutId,
            'fingerprint' => $this->fingerprint,
            'captured_at' => $this->capturedAt,
        ];
    }
}
