<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Design;

/**
 * In-memory registry of design tokens (Phase 6).
 *
 * Bound as a singleton and seeded with a sensible default set (color, spacing,
 * radius, shadow, typography, breakpoint) at construction; plugins/themes may
 * register or override tokens at runtime. Listing is available both flat
 * (keyed by "group.name") and grouped (for the editor palette).
 */
class TokenRegistry
{
    /** @var array<string, DesignToken> keyed by token key ("group.name"). */
    private array $tokens = [];

    public function __construct(bool $withDefaults = true)
    {
        if ($withDefaults) {
            $this->registerMany(self::defaults());
        }
    }

    public function register(DesignToken $token): static
    {
        $this->tokens[$token->key()] = $token;

        return $this;
    }

    /**
     * @param  array<int, DesignToken>  $tokens
     */
    public function registerMany(array $tokens): static
    {
        foreach ($tokens as $token) {
            if ($token instanceof DesignToken) {
                $this->register($token);
            }
        }

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->tokens[$key]);
    }

    public function get(string $key): ?DesignToken
    {
        return $this->tokens[$key] ?? null;
    }

    /**
     * All tokens, keyed by their dotted key.
     *
     * @return array<string, DesignToken>
     */
    public function all(): array
    {
        return $this->tokens;
    }

    /** Distinct group names in registration order. */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->tokens as $token) {
            $groups[$token->group()] = true;
        }

        return array_keys($groups);
    }

    public function flush(): static
    {
        $this->tokens = [];

        return $this;
    }

    /**
     * Grouped, serialisable view for the editor: {group: {name: value}}.
     *
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        $grouped = [];
        foreach ($this->tokens as $token) {
            $grouped[$token->group()][$token->name()] = $token->value();
        }

        return $grouped;
    }

    /**
     * The breakpoint tokens as a {name: value} map (editor convenience).
     *
     * @return array<string, mixed>
     */
    public function breakpoints(): array
    {
        return $this->toArray()['breakpoint'] ?? [];
    }

    /**
     * The default first-party token set.
     *
     * @return array<int, DesignToken>
     */
    public static function defaults(): array
    {
        return [
            // Colors — semantic palette (Phase 23). Existing keys/values kept for
            // backward compatibility; new semantic roles added.
            new DesignToken('color', 'primary', '#2563eb', 'Primary'),
            new DesignToken('color', 'secondary', '#7c3aed', 'Secondary'),
            new DesignToken('color', 'accent', '#f59e0b', 'Accent'),
            new DesignToken('color', 'background', '#ffffff', 'Background'),
            new DesignToken('color', 'surface', '#f8fafc', 'Surface'),
            new DesignToken('color', 'text', '#111827', 'Text'),
            new DesignToken('color', 'muted', '#6b7280', 'Muted'),
            new DesignToken('color', 'border', '#e5e7eb', 'Border'),
            new DesignToken('color', 'success', '#16a34a', 'Success'),
            new DesignToken('color', 'warning', '#d97706', 'Warning'),
            new DesignToken('color', 'danger', '#dc2626', 'Danger'),

            // Spacing scale.
            new DesignToken('spacing', 'xs', '0.25rem', 'Extra small'),
            new DesignToken('spacing', 'sm', '0.5rem', 'Small'),
            new DesignToken('spacing', 'md', '1rem', 'Medium'),
            new DesignToken('spacing', 'lg', '2rem', 'Large'),
            new DesignToken('spacing', 'xl', '4rem', 'Extra large'),
            new DesignToken('spacing', '2xl', '6rem', '2X large'),

            // Radius scale.
            new DesignToken('radius', 'sm', '0.25rem', 'Small'),
            new DesignToken('radius', 'md', '0.5rem', 'Medium'),
            new DesignToken('radius', 'lg', '1rem', 'Large'),
            new DesignToken('radius', 'pill', '999px', 'Pill'),

            // Shadows.
            new DesignToken('shadow', 'sm', '0 1px 2px rgba(0,0,0,.06)', 'Small'),
            new DesignToken('shadow', 'md', '0 4px 12px rgba(0,0,0,.10)', 'Medium'),
            new DesignToken('shadow', 'lg', '0 12px 32px rgba(0,0,0,.14)', 'Large'),

            // Typography roles (scalar font-size tokens drive CSS vars; the
            // family/weight arrays remain available to the editor palette).
            new DesignToken('typography', 'display', ['family' => 'inherit', 'weight' => '800', 'size' => 'clamp(2.5rem, 1.5rem + 3vw, 4rem)'], 'Display'),
            new DesignToken('typography', 'heading', ['family' => 'inherit', 'weight' => '700', 'size' => 'clamp(1.5rem, 1rem + 1.5vw, 2.25rem)'], 'Heading'),
            new DesignToken('typography', 'body', ['family' => 'inherit', 'weight' => '400', 'size' => '1rem'], 'Body'),
            new DesignToken('typography', 'small', ['family' => 'inherit', 'weight' => '400', 'size' => '0.875rem'], 'Small'),
            new DesignToken('typography', 'button', ['family' => 'inherit', 'weight' => '600', 'size' => '1rem'], 'Button'),

            // Container widths.
            new DesignToken('container', 'narrow', '42rem', 'Narrow'),
            new DesignToken('container', 'normal', '64rem', 'Normal'),
            new DesignToken('container', 'wide', '80rem', 'Wide'),
            new DesignToken('container', 'full', '100%', 'Full'),

            new DesignToken('breakpoint', 'desktop', '1280px', 'Desktop'),
            new DesignToken('breakpoint', 'tablet', '768px', 'Tablet'),
            new DesignToken('breakpoint', 'mobile', '480px', 'Mobile'),
        ];
    }
}
