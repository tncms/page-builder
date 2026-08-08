<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Builder\ResourcePicker;

/**
 * Bridges the Builder to the Design Library in Picker mode (Phase 26.47B).
 *
 * It produces the URL that opens the (unchanged) Design Library resource browser
 * scoped to a single context — a resource kind, Picker mode, and (for pages) the
 * owner document. It performs NO mutation: the Builder owns every operation and
 * the Design Library only returns a resource (Rule 1). The URL reuses the
 * existing `page-builder.library` route — there is no second picker, repository,
 * or preview.
 */
final class BuilderResourcePicker
{
    /**
     * The Design Library URL that opens the picker for a Builder context.
     */
    public function pickerUrl(PickerContext $context): string
    {
        $params = [
            'kind' => $context->kind,
            'mode' => PickerMode::Picker->value,
        ];

        // A full-page import writes into the owner's draft, so the owner must ride
        // along. Section/block picks carry no owner — the Builder owns insertion.
        if ($context->isFullPage() && $context->hasOwner()) {
            $params['ownerType'] = (string) $context->ownerType;
            $params['ownerId'] = (string) $context->ownerId;
        }

        return $this->libraryBase().'?'.http_build_query($params);
    }

    /**
     * Boot payload for the editor-side bridge: a picker URL per Builder context.
     * The page URL is owner-bound (import target); section/block are owner-free.
     *
     * @return array<string, mixed>
     */
    public function bootData(?string $ownerType, int|string|null $ownerId): array
    {
        $page = ($ownerType !== null && $ownerType !== '' && $ownerId !== null && (string) $ownerId !== '')
            ? PickerContext::page($ownerType, $ownerId)
            : null;

        return [
            'insertEvent' => 'page-builder:insert-resource',
            'importedEvent' => 'page-builder:template-imported',
            'contexts' => [
                'page' => $page !== null ? $this->pickerUrl($page) : null,
                'section' => $this->pickerUrl(PickerContext::section()),
                'block' => $this->pickerUrl(PickerContext::block()),
            ],
        ];
    }

    private function libraryBase(): string
    {
        try {
            return route('page-builder.library');
        } catch (\Throwable) {
            return url('page-builder/library');
        }
    }
}
