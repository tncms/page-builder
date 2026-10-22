<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use InvalidArgumentException;

/**
 * Trusted, server-built execution context for one data-source fetch (PB-XREG-2).
 *
 * `locale` is the render locale Page Builder already resolved (frontend public
 * locale, or the editing locale scoped around an editor render) — never a value
 * read from the browser by the provider. `mode` tells the provider whether this is
 * a public page or an authenticated editor preview. Visibility is identical in
 * both (public records only); the mode exists for diagnostics and future
 * capability gates.
 */
final class DataSourceContext
{
    public const MODE_PUBLIC = 'public';

    public const MODE_PREVIEW = 'preview';

    public function __construct(
        public readonly string $locale,
        public readonly string $mode,
    ) {
        if (! in_array($mode, [self::MODE_PUBLIC, self::MODE_PREVIEW], true)) {
            throw new InvalidArgumentException('Unsupported data-source context mode.');
        }

        if (trim($locale) === '' || strlen($locale) > 16) {
            throw new InvalidArgumentException('A data-source context requires a bounded locale.');
        }
    }

    public function isPreview(): bool
    {
        return $this->mode === self::MODE_PREVIEW;
    }
}
