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
        // Generation-only built-in Template-Picker preview (PB-FREE-LIBRARY-DESIGN-1-F).
        // The three built-in templates are client-side TypeScript definitions, not
        // repository resources, so they resolve to a canonical normalized payload rendered
        // by the SAME preview engine — never a second/hand-authored renderer. sampleContent
        // is off (the payload is the real, applied content), so the thumbnail depicts Apply.
        if ($source === 'builtin-template') {
            $id = (string) ($reference ?? '');
            $payload = (new BuiltinTemplatePreviewCatalog)->payload($id);
            if ($payload === null) {
                throw PreviewResourceException::unknownType('template', $id);
            }

            return new PreviewContext(
                kind: PreviewContext::KIND_PAGE,
                payload: $payload,
                sampleContent: false,
                label: 'builtin-template:'.$id,
            );
        }

        $rid = $this->ridFor($source, $reference);

        try {
            $resource = $this->repository->find($rid);
        } catch (ResourceNotFoundException) {
            throw PreviewResourceException::unknownType($this->kindOf($source, $rid), $reference ?? $rid);
        }

        return new PreviewContext(
            kind: $resource->kind,
            payload: $resource->payload,
            // Preview/apply fidelity (PB-FREE-LIBRARY-DESIGN-1-F). A curated,
            // browsable resource already carries its canonical authored content, so
            // its preview (and the generated catalog thumbnail) must render that
            // payload verbatim — never renderer-injected sample content that Apply
            // does not produce. Only the raw component substrate (browsable=false),
            // previewed as an empty palette component, keeps sample-content chrome so
            // an empty block still shows a representative placeholder.
            sampleContent: ! $resource->browsable,
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
