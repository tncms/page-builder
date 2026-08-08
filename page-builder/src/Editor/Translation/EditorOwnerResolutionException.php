<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Editor\Translation;

use Plugins\PageBuilder\LayoutTranslation\LayoutTranslationResolutionFault;
use RuntimeException;

/**
 * Fail-closed rejection of an invalid / forged editor translation context (LT-M1B).
 *
 * Thrown when the server cannot resolve a legitimate effective layout owner from
 * the canonical page + requested locale: an unsupported owner type, a disabled or
 * unknown locale, a locale with no Core ContentTranslation, or a failed clone. The
 * controller maps this to an HTTP 4xx — the editor never falls back to editing the
 * source layout for a bad context.
 */
final class EditorOwnerResolutionException extends RuntimeException
{
    private function __construct(
        public readonly string $reason,
        public readonly int $statusCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function fromFault(?LayoutTranslationResolutionFault $fault, ?string $requestedLocale): self
    {
        $reason = $fault?->value ?? 'unresolvable';

        return new self(
            $reason,
            422,
            "Cannot resolve a layout owner for locale [{$requestedLocale}]: {$reason}.",
        );
    }

    public static function translationNotFound(string $locale): self
    {
        return new self(
            'translation_not_found',
            422,
            "No content translation exists for locale [{$locale}] in this translation group.",
        );
    }

    public static function cloneFailed(string $outcome): self
    {
        return new self(
            'clone_failed:' . $outcome,
            409,
            "Could not provision the translated layout (clone outcome: {$outcome}).",
        );
    }
}
