<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support\DynamicStyle;

/**
 * Immutable, already-validated CSS declaration set (Phase 26.2.1).
 *
 * A declaration is a canonical `property => value` map that has passed the
 * {@see DynamicStyleNormalizer} whitelist + value validation. It is sorted by
 * property so that logically-identical styling authored in any order produces
 * one identical object (and therefore one identical hash / class). Construction
 * is private: the only way to obtain one is {@see fromCanonical}, which trusts
 * its input — every public entry point (the registry) goes through the
 * normalizer first, so an unsafe value can never reach this DTO.
 */
final class DynamicStyleDeclaration
{
    /** @param array<string, string> $declarations canonical (sorted, validated) */
    private function __construct(public readonly array $declarations) {}

    /**
     * Wrap an already-normalized, already-validated, sorted map. Callers MUST
     * pass canonical data ({@see DynamicStyleNormalizer::normalize}).
     *
     * @param  array<string, string>  $declarations
     */
    public static function fromCanonical(array $declarations): self
    {
        return new self($declarations);
    }

    public function isEmpty(): bool
    {
        return $this->declarations === [];
    }

    /**
     * Deterministic `prop:value;prop:value;` string in canonical order — the
     * hash input ({@see DynamicStyleHasher}) and the CSS rule body
     * ({@see DynamicStyleGenerator}). Order-independent because the map is sorted.
     */
    public function toCss(): string
    {
        $css = '';

        foreach ($this->declarations as $property => $value) {
            $css .= $property.':'.$value.';';
        }

        return $css;
    }
}
