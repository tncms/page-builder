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
        // PB-CORE-AUTHORITY-REMEDIATION-R1 — structured, non-sensitive detail for a
        // refusal the UI must act on (the blank-layout decision); empty otherwise.
        public readonly array $data = [],
    ) {
        parent::__construct($message);
    }

    /**
     * The source language has no Page Builder content to copy (`absent` or `empty`),
     * so creating the translated layout needs the user's explicit blank-layout
     * decision. Thrown BEFORE any write; the caller presents the choices.
     */
    public static function blankDecisionRequired(string $locale, string $sourceLayout): self
    {
        return new self(
            'pb_blank_layout_decision_required',
            409,
            'The source language has no Page Builder content to copy. Would you like to create a blank translated Page Builder layout and start designing?',
            ['source_layout' => $sourceLayout, 'locale' => $locale],
        );
    }

    /** The explicit blank layout could not be provisioned; the whole unit rolled back. */
    public static function blankProvisionFailed(string $outcome): self
    {
        return new self(
            'blank_layout_failed:' . $outcome,
            409,
            'Could not create the blank translated layout. Nothing was changed; please try again.',
        );
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

    public static function translationDisabled(): self
    {
        return new self(
            'translation_disabled',
            422,
            'Per-locale layouts are disabled; there is no translation owner to create.',
        );
    }

    public static function sourceLocaleNotCreatable(string $locale): self
    {
        return new self(
            'source_locale',
            422,
            "Locale [{$locale}] is the source locale; it is not a creatable translation.",
        );
    }

    public static function layoutChoiceInvalid(): self
    {
        return new self(
            'layout_choice_invalid',
            422,
            'Unknown layout choice.',
        );
    }

    public static function localeRequired(): self
    {
        return new self(
            'locale_required',
            422,
            'A target locale is required.',
        );
    }
}
