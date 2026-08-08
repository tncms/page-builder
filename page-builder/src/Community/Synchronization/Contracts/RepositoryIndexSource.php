<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Synchronization\Contracts;

use Plugins\PageBuilder\Community\Synchronization\RepositoryIndex;

/**
 * A transport-neutral source of a remote {@see RepositoryIndex} (Phase 26.50C, Rules
 * 3 & 12).
 *
 * This is the ONLY "fetch" seam of the synchronization protocol. It makes NO
 * assumptions about transport: an implementation may read a local file, an enterprise
 * mirror, a portable package or an HTTP endpoint — the protocol only requires that a
 * Repository Index (metadata) be produced. It never transfers payload, downloads or
 * installs anything (Rule 16).
 *
 * @see docs/synchronization-protocol.md
 */
interface RepositoryIndexSource
{
    /**
     * Fetch the remote Repository Index for the given repository. Implementations
     * return metadata only.
     */
    public function fetch(string $repository): RepositoryIndex;
}
