<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support\DynamicStyle;

/**
 * Emits CSS text for registered declarations (Phase 26.2.1).
 *
 * Pure formatting: given a class and a (already-validated) declaration it returns
 * one rule. The values are trusted because they came through
 * {@see DynamicStyleNormalizer}, so no escaping happens here — the safety lives
 * at the normalizer boundary, not the string builder.
 *
 * The selector REPEATS the class three times (`.pb-ds-x.pb-ds-x.pb-ds-x`) so the
 * rule has specificity (0,3,0). Builder styling must beat the component
 * stylesheet's base rules (single class `.pb-section{padding…}`, (0,1,0)) AND its
 * two-class structural rules (`.pb-layout-container--row.pb-container--flex{
 * align-items:stretch}`, (0,2,0)) the same way the now-stripped inline styles
 * used to — regardless of head source order. Repeating the class is the standard
 * specificity-bump technique and needs no `!important` (which is denied anyway).
 */
final class DynamicStyleGenerator
{
    public function rule(string $class, DynamicStyleDeclaration $declaration, bool $debug = false): string
    {
        $selector = '.'.$class.'.'.$class.'.'.$class;

        // Debug mapping (Phase 26.2.2, Part 7): trace a hashed class back to its
        // properties. Only property NAMES appear (all from the fixed whitelist, so
        // none can contain `*/`); values are never echoed into the comment.
        $comment = $debug
            ? '/* '.$class.' → '.implode(', ', array_keys($declaration->declarations)).' */'
            : '';

        return $comment.$selector.'{'.$declaration->toCss().'}';
    }

    /**
     * One rule per registered class, concatenated. Empty string for no rules.
     *
     * @param  array<string, DynamicStyleDeclaration>  $rules  class => declaration
     */
    public function stylesheet(array $rules, bool $debug = false): string
    {
        $css = '';

        foreach ($rules as $class => $declaration) {
            $css .= $this->rule($class, $declaration, $debug);
        }

        return $css;
    }
}
