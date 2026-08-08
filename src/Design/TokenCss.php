<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Design;

/**
 * Renders the design-token registry into a compact `:root { --pb-* }` block
 * (Phase 23). This is the single source of the CSS custom properties that
 * `page-builder.css` and every component style consume, so raw values are never
 * hardcoded across Blade views.
 *
 * Naming: `--pb-<group>-<name>` — e.g. `--pb-color-primary`, `--pb-space-md`
 * (the `spacing` group is emitted under the shorter `space` prefix to match the
 * task's variable contract), `--pb-radius-lg`, `--pb-container-normal`.
 *
 * Only SCALAR (string/number) token values become variables. Structured tokens
 * (e.g. the typography family/weight/size arrays) contribute their `size` as a
 * `--pb-font-<name>` variable when present; the rest stays editor-only metadata.
 */
final class TokenCss
{
    /** Map a token group to its CSS variable prefix. */
    private const PREFIX = [
        'color' => 'color',
        'spacing' => 'space',
        'radius' => 'radius',
        'shadow' => 'shadow',
        'container' => 'container',
    ];

    public function __construct(private readonly TokenRegistry $tokens) {}

    /**
     * The full `<style>`-ready CSS string, including the `:root` selector.
     */
    public function render(): string
    {
        return ':root{'.$this->declarations().'}';
    }

    /**
     * Just the `--pb-*: value;` declarations (no selector), so callers can nest
     * them under a different scope if needed.
     */
    public function declarations(): string
    {
        $out = '';

        foreach ($this->tokens->all() as $token) {
            $group = $token->group();
            $value = $token->value();

            if (isset(self::PREFIX[$group]) && $this->isScalar($value)) {
                $out .= '--pb-'.self::PREFIX[$group].'-'.$token->name().':'.$this->clean((string) $value).';';
            }

            // Typography: expose the font-size as --pb-font-<name>.
            if ($group === 'typography' && is_array($value) && isset($value['size']) && $this->isScalar($value['size'])) {
                $out .= '--pb-font-'.$token->name().':'.$this->clean((string) $value['size']).';';
            }
        }

        return $out;
    }

    private function isScalar(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_float($value);
    }

    /**
     * Strip characters that could break out of the CSS value context. Token
     * values are author-defined, but this keeps the emitted stylesheet safe if a
     * plugin registers a hostile token.
     */
    private function clean(string $value): string
    {
        return trim(str_replace(['<', '>', '{', '}', ';'], '', $value));
    }
}
