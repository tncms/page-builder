<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Catalog;

use RuntimeException;

/** The unified catalog was read before the specialized registries froze (PB-XREG-4). */
final class CatalogNotReadyException extends RuntimeException {}
