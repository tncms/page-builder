<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Preview;

use Plugins\PageBuilder\Repository\Contracts\DesignResourceRepositoryInterface;
use Plugins\PageBuilder\Repository\Exceptions\ResourceNotFoundException;
use Plugins\PageBuilder\Repository\Providers\LocalResourceProvider;
use Plugins\PageBuilder\Repository\Rid;

/**
 * Resolves a preview request into a PreviewContext by consuming the Design
 * Resource Repository — the single source of truth. It no longer scans the
 * component registries directly (that is the LocalResourceProvider's job).
 *
 * Request shapes (kept stable for the preview iframe endpoint):
 *   - source "sample"          → RID builtin:page:sample
 *   - source "section", ref X  → RID builtin:section:X
 *   - source "block",  ref X   → RID builtin:block:X
 *   - source "rid",    ref R   → the RID R verbatim (Design-Library friendly)
 *
 * The PreviewEngine is unchanged: it still consumes a PreviewContext.
 */
final class PreviewResourceResolver
{
    public function __construct(
        private readonly DesignResourceRepositoryInterface $repository,
    ) {
    }

    /**
     * @throws PreviewResourceException
     */
    public function resolve(string $source, ?string $reference = null): PreviewContext
    {
        $rid = $this->ridFor($source, $reference);

        try {
            $resource = $this->repository->find($rid);
        } catch (ResourceNotFoundException) {
            throw PreviewResourceException::unknownType($this->kindOf($source, $rid), $reference ?? $rid);
        }

        return new PreviewContext(
            kind: $resource->kind,
            payload: $resource->payload,
            label: $resource->rid,
        );
    }

    private function ridFor(string $source, ?string $reference): string
    {
        return match ($source) {
            'sample' => LocalResourceProvider::SOURCE.':'.Rid::KIND_PAGE.':'.LocalResourceProvider::SAMPLE_ID,
            'section' => 'builtin:'.Rid::KIND_SECTION.':'.$this->requireReference($source, $reference),
            'block' => 'builtin:'.Rid::KIND_BLOCK.':'.$this->requireReference($source, $reference),
            'rid' => $this->requireReference($source, $reference),
            default => throw PreviewResourceException::unknownSource($source),
        };
    }

    private function kindOf(string $source, string $rid): string
    {
        return match ($source) {
            'section' => Rid::KIND_SECTION,
            'block' => Rid::KIND_BLOCK,
            'sample' => Rid::KIND_PAGE,
            default => Rid::isValid($rid) ? Rid::parse($rid)->kind : 'resource',
        };
    }

    private function requireReference(string $source, ?string $reference): string
    {
        $reference = is_string($reference) ? trim($reference) : '';
        if ($reference === '') {
            throw PreviewResourceException::missingReference($source);
        }

        return $reference;
    }
}
