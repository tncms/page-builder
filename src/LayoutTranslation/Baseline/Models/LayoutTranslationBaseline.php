<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Baseline\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the baseline table (LT-4).
 *
 * Layer: Translation (baseline / database model). Implementation detail used ONLY
 * inside {@see \Plugins\PageBuilder\LayoutTranslation\Baseline\Repositories\EloquentLayoutBaselineRepository};
 * never returned across the repository boundary. Separate from the frozen LT-2
 * binding table.
 *
 * @property int $id
 * @property string $page_translation_key
 * @property int $source_layout_id
 * @property string $fingerprint
 * @property string $captured_at
 */
class LayoutTranslationBaseline extends Model
{
    protected $table = 'pb_layout_translation_baselines';

    protected $fillable = [
        'page_translation_key',
        'source_layout_id',
        'fingerprint',
        'captured_at',
    ];

    protected $casts = [
        'source_layout_id' => 'integer',
    ];
}
