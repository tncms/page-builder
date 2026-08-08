<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Cloning\Adapters;

use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Cloning\Results\SourceLayoutReadResult;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Persistence\Contracts\LayoutTranslationBindingReaderInterface;
use Plugins\PageBuilder\Models\PageBuilderLayout;
use Plugins\PageBuilder\Support\LayoutStore;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;

/**
 * Reads a source page translation's effective layout document (LT-3).
 *
 * Layer: Translation (adapter). It resolves the source document via the source
 * binding (`LayoutKey → pb_layouts.id`) or, for the group's default locale with no
 * binding yet, the legacy `(owner_type, owner_id)` row. It selects the payload
 * deterministically (published → draft → unavailable) and returns a COPY of the
 * payload array — never the Eloquent model. It never mutates the source.
 */
final class PageBuilderSourceLayoutDocumentReader implements SourceLayoutDocumentReaderInterface
{
    public function __construct(
        private readonly LayoutTranslationBindingReaderInterface $bindings,
        private readonly LayoutStore $layouts,
        private readonly LanguageConfigurationContract $languages,
    ) {}

    public function read(PageTranslationKey $source): SourceLayoutReadResult
    {
        $binding = $this->bindings->findByPageTranslationKey($source);

        if ($binding !== null) {
            $layout = PageBuilderLayout::query()->whereKey($binding->layoutId())->first();

            return $layout === null ? SourceLayoutReadResult::notFound() : $this->select($layout);
        }

        // No binding: only the group's default locale maps to the legacy document.
        $default = $this->languages->normalize($this->languages->defaultLocale());
        if ($source->locale() !== $default) {
            return SourceLayoutReadResult::notFound();
        }

        $layout = $this->layouts->find($source->group()->ownerType, $source->group()->ownerId);

        return $layout === null ? SourceLayoutReadResult::notFound() : $this->select($layout);
    }

    private function select(PageBuilderLayout $layout): SourceLayoutReadResult
    {
        if ($this->isValidDocument($layout->published_payload)) {
            return SourceLayoutReadResult::found($layout->published_payload, 'published');
        }

        if ($this->isValidDocument($layout->draft_payload)) {
            return SourceLayoutReadResult::found($layout->draft_payload, 'draft');
        }

        return SourceLayoutReadResult::payloadUnavailable();
    }

    private function isValidDocument(mixed $payload): bool
    {
        return is_array($payload) && is_array($payload['sections'] ?? null);
    }
}
