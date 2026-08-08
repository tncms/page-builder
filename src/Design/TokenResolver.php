<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Design;

/**
 * Resolves token references to their concrete values (Phase 6).
 *
 * A reference is either a bare dotted key ("color.primary") or the wrapped form
 * "{{color.primary}}". Anything that is not a known reference is passed through
 * unchanged (so literal CSS values keep working). Unknown references fall back
 * to the supplied default (or the original string when no default is given).
 */
final class TokenResolver
{
    public function __construct(private readonly TokenRegistry $tokens) {}

    /**
     * True when the value looks like a token reference this resolver handles.
     */
    public function isReference(mixed $value): bool
    {
        return is_string($value) && $this->normalizeKey($value) !== null;
    }

    /**
     * Resolve a value: token references become their token value; everything
     * else is returned as-is. Unknown references fall back to $fallback (or the
     * raw value when $fallback is null).
     */
    public function resolve(mixed $value, mixed $fallback = null): mixed
    {
        $key = is_string($value) ? $this->normalizeKey($value) : null;

        if ($key === null) {
            return $value;
        }

        $token = $this->tokens->get($key);

        if ($token !== null) {
            return $token->value();
        }

        return $fallback ?? $value;
    }

    /**
     * Extract the dotted key from a reference, or null when it is not one.
     */
    private function normalizeKey(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^\{\{\s*([a-z0-9_-]+\.[a-z0-9_-]+)\s*\}\}$/i', $value, $m) === 1) {
            return $m[1];
        }

        if (preg_match('/^[a-z0-9_-]+\.[a-z0-9_-]+$/i', $value) === 1) {
            return $value;
        }

        return null;
    }
}
