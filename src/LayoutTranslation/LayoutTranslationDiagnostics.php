<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation;

use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;
use Throwable;

/**
 * Never-throwing diagnostics for the Layout Translation foundation (LT-1, locked).
 *
 * Mirrors the Core translation diagnostics convention: a single report() safe to
 * call anytime that degrades every failure into an 'error' entry rather than
 * throwing. For a given owner it exposes, per locale and without any persistence
 * dependency: the descriptor, its identity keys, the existence / lifecycle /
 * synchronization dimensions, the capabilities, and the validation result.
 */
final class LayoutTranslationDiagnostics
{
    private const FEATURE = 'Page Builder Layout Translation (LT-1)';

    public function __construct(
        private readonly LayoutTranslationConfig $config,
        private readonly LanguageConfigurationContract $languages,
        private readonly LayoutTranslationResolver $resolver,
        private readonly LayoutRelationshipValidator $validator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(?string $ownerType = null, int|string|null $ownerId = null): array
    {
        try {
            $report = [
                'feature' => self::FEATURE,
                'enabled' => $this->config->enabled(),
                'source_locale' => $this->languages->defaultLocale(),
                'enabled_locales' => $this->languages->enabledLocales(),
                'multilingual' => $this->languages->multilingualEnabled(),
            ];

            if ($ownerType !== null && $ownerId !== null) {
                $report['owner'] = $this->ownerReport($ownerType, $ownerId);
            }

            return $report;
        } catch (Throwable $e) {
            return [
                'feature' => self::FEATURE,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerReport(string $ownerType, int|string $ownerId): array
    {
        $perLocale = [];
        $groupKey = null;

        foreach ($this->languages->enabledLocales() as $locale) {
            $descriptor = $this->resolver->resolve($ownerType, $ownerId, $locale);
            $validation = $this->validator->validate($descriptor);
            $groupKey ??= $descriptor->group()->value();

            $perLocale[$locale] = [
                'descriptor' => $descriptor->toArray(),
                'identity' => [
                    'translation_group' => $descriptor->group()->value(),
                    'page_translation' => $descriptor->translation()->value(),
                    'layout' => $descriptor->layout()->value(),
                ],
                'existence' => $descriptor->existence()->value,
                'lifecycle' => $descriptor->lifecycle()?->value,
                'synchronization' => $descriptor->synchronization()->value,
                'resolvable' => $descriptor->isResolvable(),
                'capabilities' => $descriptor->capabilities()->toArray(),
                'validation' => $validation->passes() ? 'valid' : $validation->issueCodes(),
            ];
        }

        return [
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'translation_group' => $groupKey,
            'locales' => $perLocale,
        ];
    }
}
