<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

/**
 * Outcome of {@see TranslationOwnerCreator::create()}: the resolved translation
 * owner, its layout provisioning result, and whether this request created the
 * Core content translation (false when it already existed).
 */
final class TranslationOwnerCreation
{
    public function __construct(
        public readonly EffectiveLayoutOwner $owner,
        public readonly LayoutProvisionResult $provision,
        public readonly bool $created,
        // PB-CORE37-R1 — the committed Core revision after this request, so the editor
        // adopts current state instead of saving against an obsolete revision.
        public readonly ?int $contentRevision = null,
    ) {}
}
