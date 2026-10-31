<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Filament\Concerns;

use Filament\Notifications\Notification;
use Livewire\Attributes\Locked;
use Plugins\PageBuilder\Authority\AuthorityResponse;
use Plugins\PageBuilder\Authority\PageBuilderAuthorityException;
use Plugins\PageBuilder\Editor\Translation\BlankLayoutDecision;
use Plugins\PageBuilder\Editor\Translation\EditorOwnerResolutionException;
use Plugins\PageBuilder\Editor\Translation\TranslationOwnerCreator;
use Plugins\PageBuilder\Support\EditableOwners;
use Plugins\PageBuilder\Support\EditorUrls;
use TheNguyen\CMS\ContentAuthority\ContentWriteException;
use TheNguyen\CMS\Localization\Contracts\LanguageConfigurationContract;

/**
 * The blank-layout decision on the editor pages (PB-CORE-AUTHORITY-REMEDIATION-R1, C).
 *
 * Opening a translation whose source language has no Page Builder content to copy
 * shows the decision instead of an error page: Create blank translation (an
 * explicit action, re-authorized), Return to content editor, or Cancel. Showing it
 * writes nothing. Requires the host page's `$ownerType`, `$ownerId` and
 * `$requestedLocale`.
 */
trait PresentsBlankLayoutDecision
{
    /**
     * @var array{source_layout: string, locale: string, choices: list<string>, return_url: ?string, cancel_url: ?string}|null
     */
    #[Locked]
    public ?array $blankDecision = null;

    /** True when the refusal was the blank decision (now presented), false otherwise. */
    protected function presentBlankDecision(EditorOwnerResolutionException $e): bool
    {
        $owner = EditableOwners::resolve($this->ownerType, $this->ownerId);
        if ($owner === null || ! BlankLayoutDecision::isDecision($e)) {
            return false;
        }

        $this->blankDecision = BlankLayoutDecision::payload($e, $this->ownerType, $owner, $this->decisionSourceLocale()) + [
            'cancel_url' => $this->decisionEditorUrl($this->decisionSourceLocale()),
        ];

        return true;
    }

    /** The explicit "Create blank translation" choice. */
    public function createBlankTranslationLayout(): void
    {
        abort_unless(static::canAccess() && $this->blankDecision !== null, 403);

        $owner = EditableOwners::resolve($this->ownerType, $this->ownerId);
        abort_if($owner === null, 404);

        try {
            $result = app(TranslationOwnerCreator::class)->create(
                $this->ownerType,
                $owner,
                (string) $this->blankDecision['locale'],
                auth()->user(),
                $this->contentRevision,
                TranslationOwnerCreator::LAYOUT_BLANK,
            );
        } catch (PageBuilderAuthorityException|ContentWriteException|EditorOwnerResolutionException $e) {
            Notification::make()
                ->danger()
                ->title(__('Could not create the blank translation'))
                ->body($e instanceof EditorOwnerResolutionException ? __($e->getMessage()) : AuthorityResponse::message($e))
                ->send();

            return;
        }

        $url = $this->decisionEditorUrl($result->owner->locale);
        if ($url === null) {
            Notification::make()->success()->title(__('Blank translation created'))->send();

            return;
        }

        $this->redirect($url);
    }

    private function decisionEditorUrl(string $locale): ?string
    {
        try {
            return EditorUrls::withLocale(static::getUrl(['ownerType' => $this->ownerType, 'ownerId' => $this->ownerId]), $locale);
        } catch (\Throwable) {
            return null;
        }
    }

    private function decisionSourceLocale(): string
    {
        $languages = app(LanguageConfigurationContract::class);

        return $languages->normalize($languages->defaultLocale());
    }
}
