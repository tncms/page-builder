<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources\Contracts;

/**
 * Marker for a first-party Page Builder data-source provider that owns the reserved
 * `page-builder` namespace (PB-XREG-2). External plugins never implement this. Page
 * Builder ships no built-in sources today; the marker only reserves the authority.
 */
interface InternalDataSourceProvider extends DataSourceProvider {}
