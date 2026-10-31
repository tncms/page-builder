<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use TheNguyen\CMS\Models\Content;

/**
 * The set of content owner types the Page Builder can edit (Phase 5).
 *
 * Owners are TN CMS Content records discriminated by their `type` column. Only
 * `page` and `post` are supported for now; this small map is the single source
 * of truth for what the editor route + form integration will accept, keeping
 * owner-type validation in one guarded place.
 */
final class EditableOwners
{
    /**
     * Supported owner types, keyed by the `type` used in routes / LayoutStore,
     * mapped to a human label.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'page' => 'Page',
            'post' => 'Post',
        ];
    }

    /**
     * The supported owner-type identifiers.
     *
     * @return array<int, string>
     */
    public static function types(): array
    {
        return array_keys(self::all());
    }

    public static function isSupported(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    /**
     * Human label for an owner type (falls back to the ucfirst'd type).
     */
    public static function label(string $type): string
    {
        return self::all()[$type] ?? ucfirst($type);
    }

    /**
     * The Core permission that governs editing an owner type's content records
     * (the same slug the Core Pages/Posts admin resources check), or null when
     * the type is unsupported.
     */
    public static function coreEditPermission(string $type): ?string
    {
        return match ($type) {
            'page' => 'pages.edit',
            'post' => 'posts.edit',
            default => null,
        };
    }

    /**
     * Whether the current user may create a content translation for an owner type
     * from the editor: a Core content write, so it needs BOTH `page-builder.edit`
     * and the type's Core edit permission.
     */
    public static function canCreateTranslation(string $type): bool
    {
        $core = self::coreEditPermission($type);

        return $core !== null
            && function_exists('cms_can')
            && cms_can('page-builder.edit')
            && cms_can($core);
    }

    /**
     * Resolve the owning Content record for a type + id, or null when the type
     * is unsupported or no matching record exists. Never throws.
     */
    public static function resolve(string $type, int|string $id): ?Content
    {
        if (! self::isSupported($type)) {
            return null;
        }

        return Content::query()
            ->where('type', $type)
            ->whereKey($id)
            ->first();
    }

    /**
     * Best-effort Filament "edit" URL for the owning Content record, used as the
     * editor's exit target. Returns null when the resource/route can't be
     * resolved (the editor then falls back to browser history). Never throws.
     */
    public static function editUrl(string $type, Content $owner): ?string
    {
        $resource = match ($type) {
            'page' => \App\Filament\Admin\Resources\PageResource::class,
            'post' => \App\Filament\Admin\Resources\PostResource::class,
            default => null,
        };

        if ($resource === null || ! class_exists($resource)) {
            return null;
        }

        try {
            return $resource::getUrl('edit', ['record' => $owner->getKey()]);
        } catch (\Throwable) {
            return null;
        }
    }
}
