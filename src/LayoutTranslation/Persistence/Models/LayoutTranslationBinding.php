<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the translation-binding table — LT-2.
 *
 * Layer: Persistence / Database Model. This class is an implementation detail used
 * ONLY inside {@see \Plugins\PageBuilder\LayoutTranslation\Persistence\Repositories\EloquentLayoutTranslationBindingRepository};
 * it is never returned across the repository boundary and no LT-1 domain value
 * object depends on it. The table owns the translation relationship and the
 * persisted, three-dimensional translation status — never a layout document
 * (those remain in pb_layouts).
 *
 * @property int $id
 * @property string $translation_group_key
 * @property string $page_translation_key
 * @property string $layout_key
 * @property int $layout_id
 * @property string $locale
 * @property string $existence_state
 * @property string|null $lifecycle_state
 * @property string $synchronization_state
 */
class LayoutTranslationBinding extends Model
{
    protected $table = 'pb_layout_translation_bindings';

    protected $fillable = [
        'translation_group_key',
        'page_translation_key',
        'layout_key',
        'layout_id',
        'locale',
        'existence_state',
        'lifecycle_state',
        'synchronization_state',
    ];

    protected $casts = [
        'layout_id' => 'integer',
    ];
}
