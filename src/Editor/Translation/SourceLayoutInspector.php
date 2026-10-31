<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

use Plugins\PageBuilder\LayoutTranslation\Cloning\Contracts\SourceLayoutDocumentReaderInterface;
use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Identity\TranslationGroupKey;

/**
 * Classifies the source-language Page Builder document a translation would be
 * created from (PB-CORE-AUTHORITY-REMEDIATION-R1, C).
 *
 * Read-only, through the frozen LT-3 source reader — the SAME document the clone
 * service would copy (published first, then draft):
 *  - `absent`    — no readable source document (no layout row, or no valid payload);
 *  - `empty`     — a valid source document with no sections;
 *  - `cloneable` — a source document with content to copy.
 *
 * Only `cloneable` may be provisioned silently. `absent` and `empty` require the
 * user's explicit blank-layout decision; the server never guesses it.
 */
final class SourceLayoutInspector
{
    public const ABSENT = 'absent';

    public const EMPTY = 'empty';

    public const CLONEABLE = 'cloneable';

    public function __construct(
        private readonly SourceLayoutDocumentReaderInterface $source,
    ) {}

    public function classify(TranslationGroupKey $group, string $sourceLocale): string
    {
        $read = $this->source->read(PageTranslationKey::forGroup($group, $sourceLocale));

        if (! $read->isFound()) {
            return self::ABSENT;
        }

        return ($read->payload()['sections'] ?? []) === [] ? self::EMPTY : self::CLONEABLE;
    }

    public function classifyFor(EffectiveLayoutOwner $effective): string
    {
        return $this->classify($effective->group, $effective->sourceLocale);
    }
}
