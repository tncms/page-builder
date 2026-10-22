<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources\Contracts;

/**
 * The public contract a plugin implements to contribute read-only dynamic data
 * sources to Page Builder (PB-XREG-2).
 *
 * A provider is a pure description: it declares a namespace (which MUST equal the
 * canonical installed plugin slug — verified server-side, never trusted from this
 * value), a version, and a deterministic list of {@see DataSourceDefinition}s.
 * Registration goes through {@see \Plugins\PageBuilder\DataSources\DataSourceRegistration}.
 *
 * Deliberately separate from the Component Provider and the Resource Provider SDK:
 * a plugin may implement any combination, each through its own registry.
 */
interface DataSourceProvider
{
    /** The provider's namespace — its canonical installed plugin slug. */
    public function namespace(): string;

    /** The provider's own data-source contract version (e.g. `1.0.0`). */
    public function version(): string;

    /**
     * Every source this provider contributes. Must be deterministic: the same
     * definitions on every call.
     *
     * @return iterable<DataSourceDefinition>
     */
    public function sources(): iterable;
}
