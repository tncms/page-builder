<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Exceptions\AuthoredHtmlRejectedException;

/**
 * Save-time bounds for author-entered HTML in an editor draft (PB-TEXT-AUTHORING-R1).
 *
 * Walks a NORMALIZED payload and checks every per-locale value of a `text` block's
 * `content` and an `html` block's `html` against {@see AuthoredHtml::violation()}.
 * An oversized or too-deeply-nested value rejects the whole save (bounded 422); the
 * payload is never rewritten. Sanitation itself is a render-time concern (Core
 * `cms_html()` via {@see AuthoredHtml}), so the author's source round-trips intact.
 * Traversal follows the normalizer's shape and is depth-bounded.
 */
final class AuthoredHtmlLayoutValidator
{
    private const MAX_DEPTH = 32;

    /** @var array<string, string> block type → authored-HTML field */
    private const FIELDS = ['text' => 'content', 'html' => 'html'];

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws AuthoredHtmlRejectedException
     */
    public function assertValid(array $payload): void
    {
        foreach (is_array($payload['sections'] ?? null) ? $payload['sections'] : [] as $section) {
            if (is_array($section)) {
                $this->checkBlocks($section['blocks'] ?? null, 1);
            }
        }
    }

    private function checkBlocks(mixed $blocks, int $depth): void
    {
        if (! is_array($blocks) || $depth > self::MAX_DEPTH) {
            return;
        }

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $field = self::FIELDS[(string) ($block['type'] ?? '')] ?? null;

            if ($field !== null) {
                $this->checkValue($block, $field);
            }

            $this->checkBlocks($block['data']['blocks'] ?? null, $depth + 1);
        }
    }

    /** @param  array<string, mixed>  $block */
    private function checkValue(array $block, string $field): void
    {
        $value = $block['data'][$field] ?? null;
        $values = is_array($value) ? $value : [$value];

        foreach ($values as $html) {
            if (! is_string($html)) {
                continue;
            }

            $reason = AuthoredHtml::violation($html);

            if ($reason !== null) {
                throw new AuthoredHtmlRejectedException($reason, is_string($block['id'] ?? null) ? $block['id'] : '', $field);
            }
        }
    }
}
