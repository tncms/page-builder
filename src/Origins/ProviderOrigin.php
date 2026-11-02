<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Origins;

use InvalidArgumentException;

/**
 * Who contributed a registered Page Builder item (PB-XREG-4): Page Builder itself, an active plugin, or the
 * active theme. Always derived server-side at registration time — never read from layout data or from a
 * provider's own declaration — and kept as registry metadata only (stored layouts carry no origin field).
 *
 * Exposes bounded public metadata: the kind and the public slug. Never a path, class name or service name.
 */
final class ProviderOrigin
{
    public const BUILTIN = 'builtin';

    public const PLUGIN = 'plugin';

    public const THEME = 'theme';

    public const KINDS = [self::BUILTIN, self::PLUGIN, self::THEME];

    private const SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    private function __construct(
        public readonly string $kind,
        public readonly string $slug,
    ) {}

    public static function builtin(): self
    {
        return new self(self::BUILTIN, 'page-builder');
    }

    public static function plugin(string $slug): self
    {
        return new self(self::PLUGIN, self::assertSlug($slug));
    }

    public static function theme(string $slug): self
    {
        return new self(self::THEME, self::assertSlug($slug));
    }

    /** @return array{kind: string, slug: string} */
    public function toArray(): array
    {
        return ['kind' => $this->kind, 'slug' => $this->slug];
    }

    private static function assertSlug(string $slug): string
    {
        if (strlen($slug) > 64 || preg_match(self::SLUG, $slug) !== 1) {
            throw new InvalidArgumentException('An origin slug must be a lowercase identifier segment.');
        }

        return $slug;
    }
}
