<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Contracts;

use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;

/**
 * The official **Repository Contract** for Design Resources (Phase 26.49A, Rule 7).
 *
 * FROZEN. The repository is the single source of truth and is READ-oriented. Its
 * responsibilities are strictly: aggregate providers, resolve RIDs, query, search
 * and compose. The repository MUST NOT normalize, validate, capture, or mutate any
 * payload — those belong to the Capture Pipeline. There is exactly ONE repository;
 * no second repository may be introduced.
 *
 * This is the canonical, documented name for the repository contract. It reuses the
 * pre-existing {@see DesignResourceRepositoryInterface} (Phase 26.42) rather than
 * duplicating it — the interface it extends adds no methods.
 *
 * @see docs/repository-contract.md
 */
interface RepositoryContract extends DesignResourceRepositoryInterface
{
}
