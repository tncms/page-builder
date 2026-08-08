<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\Registry;

use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;

/**
 * Shared base for repository-backed Builder Resource providers (Phase 26.47E).
 *
 * Reads the authoritative DesignResourceRepository, keeps only palette kinds
 * (section, block — kit is reserved and never mixed in, Rule 4), and derives a
 * cheap revision from the contributed RIDs so the registry cache invalidates only
 * when the resource set actually changes (Rule 13). Subclasses declare which
 * repository sources belong to their group.
 */
abstract class AbstractRepositoryResourceProvider implements BuilderResourceProvider
{
    /** The only kinds that become palette elements. Kits are reserved (Rule 4/5). */
    protected const PALETTE_KINDS = [Rid::KIND_SECTION, Rid::KIND_BLOCK];

    public function __construct(
        protected readonly DesignResourceRepositoryInterface $repository,
    ) {
    }

    /**
     * The repository `source` values that belong to this provider's group.
     *
     * @return array<int, string>
     */
    abstract protected function sources(): array;

    public function resources(): iterable
    {
        foreach ($this->repository->all() as $resource) {
            if (! in_array($resource->source, $this->sources(), true)) {
                continue;
            }
            if (! in_array($resource->kind, self::PALETTE_KINDS, true)) {
                continue;
            }

            yield $resource;
        }
    }

    public function revision(): int
    {
        $rids = [];
        foreach ($this->resources() as $resource) {
            $rids[] = $resource->rid;
        }

        sort($rids);

        return count($rids) === 0 ? 0 : (int) (crc32(implode('|', $rids)) % 2147483647);
    }
}
