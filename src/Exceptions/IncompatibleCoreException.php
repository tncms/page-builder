<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Exceptions;

use RuntimeException;

/**
 * Thrown by {@see \Plugins\PageBuilder\PageBuilderServiceProvider::register()}
 * when the running TN CMS Core is older than {@see \Plugins\PageBuilder\Support\CoreCompatibility::MINIMUM_CORE}.
 *
 * Throwing (rather than returning) is deliberate: the Core ExtensionManager
 * registers a plugin's views, migrations and routes AFTER calling its provider's
 * register(), and isolates each plugin in a try/catch. Throwing here therefore
 * makes Core skip this plugin's views/migrations/routes as well, leaving the
 * plugin fully inactive on an unsupported Core instead of half-registered.
 */
final class IncompatibleCoreException extends RuntimeException {}
