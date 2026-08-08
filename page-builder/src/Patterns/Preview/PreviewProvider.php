<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Preview;

use Plugins\PageBuilder\Patterns\Pattern;

/**
 * Strategy for producing a {@see PatternPreview} for a given preview `type`
 * (Phase 25.1, §13). The {@see PreviewProviderRegistry} selects a provider by the
 * manifest's declared `preview.type`. Today only {@see ImagePreviewProvider} does
 * real work; the render/generated providers are registered as architecture seams.
 */
interface PreviewProvider
{
    /** The preview type this provider serves: image | render | generated. */
    public function type(): string;

    public function supports(string $type): bool;

    public function resolve(Pattern $pattern): PatternPreview;
}
