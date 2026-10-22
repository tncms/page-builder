<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components\Contracts;

/**
 * Marker for the Page Builder first-party component provider (PB-XREG-1).
 *
 * The built-in library registers through the SAME authority as external plugins
 * (one registry, one gate), but is the single trusted internal provider that may:
 *   - use the reserved `page-builder` namespace / bare built-in identifiers;
 *   - skip external plugin-slug identity derivation (its identity is Page Builder
 *     itself, not a discovered installed plugin).
 *
 * No third-party provider may implement this marker; the registration gate only
 * honours it for Page Builder's own internal adapter.
 */
interface InternalComponentProvider extends ComponentProvider {}
