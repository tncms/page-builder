<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Contracts;

use Plugins\PageBuilder\Repository\Contracts\DesignResourceProviderInterface;

/**
 * The ONE official Design Resource **Provider Contract** (Phase 26.49A, Rule 10).
 *
 * FROZEN. Every resource provider — Built-in, Imported, My Designs, and every
 * future channel (Community, Marketplace, PRO, Portable Package) — implements this
 * exact contract and exposes identical capabilities. There are no provider-specific
 * APIs: the repository composes all providers uniformly (key + priority +
 * availability + revision + all + find). A provider decides only HOW it
 * materialises resources (registry, package file, network); the surface it presents
 * is fixed.
 *
 * This is the canonical, documented name for the provider contract. It reuses the
 * pre-existing {@see DesignResourceProviderInterface} (Phase 26.42) rather than
 * duplicating it — the interface it extends adds no methods, so implementing this
 * contract is exactly implementing the established provider surface. New providers
 * SHOULD implement this contract; the repository accepts either type.
 *
 * @see docs/provider-development-guide.md
 */
interface DesignResourceProvider extends DesignResourceProviderInterface
{
}
