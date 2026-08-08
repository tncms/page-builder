<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support\DynamicStyle;

/**
 * Request-scoped registry that turns safe Builder styling into deduplicated CSS
 * classes injected into `<head>` (Phase 26.2.1) — the generic successor to the
 * width-only {@see \Plugins\PageBuilder\Support\ColumnWidthRegistry}.
 *
 * WHY THIS EXISTS: the public content body is passed through the theme's output
 * sanitizer (`cms_html()` → HtmlSanitizer, global allow-list `['class']`), which
 * strips every inline `style` attribute. Runtime visual styling authored in the
 * Builder (padding, background, alignment, …) therefore vanishes on the anonymous
 * frontend even though it looks correct in the never-sanitized Live Builder iframe.
 *
 * The fix, WITHOUT touching the sanitizer/theme/core: each element registers its
 * safe declarations here and renders the returned CLASS (which survives). The one
 * `<style>` block the middleware injects into `<head>` — outside the sanitized
 * body — carries the matching `.pb-ds-*{…}` rules, so identical styling appears in
 * the Builder, previews, and the anonymous frontend from one source of truth.
 *
 * Deduplication is by content hash: 100 identically-styled elements share ONE rule.
 * Registered as a singleton; {@see reset} runs once per render so the emitted CSS
 * reflects only the current page (safe under Octane / test reuse).
 */
final class DynamicStyleRegistry
{
    /** @var array<string, DynamicStyleDeclaration> class ("pb-ds-83f4e1") => declaration */
    private array $rules = [];

    public function __construct(
        private readonly DynamicStyleNormalizer $normalizer,
        private readonly DynamicStyleHasher $hasher,
        private readonly DynamicStyleGenerator $generator,
    ) {}

    /**
     * Validate + register a raw `property => value` map and return its stable CSS
     * class — or null when nothing survives validation (so the caller adds no
     * class and no unsafe value can ever reach the stylesheet).
     *
     * @param  array<string, mixed>  $declarations
     */
    public function register(array $declarations): ?string
    {
        $declaration = $this->normalizer->normalize($declarations);

        if ($declaration->isEmpty()) {
            return null;
        }

        $class = 'pb-ds-'.$this->hasher->hash($declaration);
        $this->rules[$class] ??= $declaration;

        return $class;
    }

    /** Discard everything collected so far (call once at the start of a render). */
    public function reset(): void
    {
        $this->rules = [];
    }

    /** Whether any declaration has been registered this request. */
    public function isEmpty(): bool
    {
        return $this->rules === [];
    }

    /**
     * One `.pb-ds-*` rule per registered declaration, safe to embed in a `<style>`
     * block (every value passed the normalizer). Empty string when nothing used.
     * Rules gain a debug comment when `page-builder.runtime_style_debug` is on.
     */
    public function cssRules(): string
    {
        return $this->generator->stylesheet($this->rules, (bool) config('page-builder.runtime_style_debug', false));
    }

    /**
     * Developer debug map (Phase 26.2.2, Part 7): each registered class mapped to
     * the property names it sets, e.g. `['pb-ds-83f1ab' => ['padding', 'background']]`.
     * For tooling / inspection only — does not affect rendered output.
     *
     * @return array<string, list<string>>
     */
    public function debugMap(): array
    {
        $map = [];

        foreach ($this->rules as $class => $declaration) {
            $map[$class] = array_keys($declaration->declarations);
        }

        return $map;
    }
}
