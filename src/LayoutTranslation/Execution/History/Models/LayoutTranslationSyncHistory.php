<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Execution\History\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the append-only synchronization history table (LT-7C).
 *
 * Layer: Translation (execution history / database model). Implementation detail used ONLY inside
 * {@see \Plugins\PageBuilder\LayoutTranslation\Execution\History\Repositories\EloquentSyncHistoryRepository};
 * never returned across the repository boundary. Append-only — rows are only ever inserted. Payload-safe:
 * `applied_items`/`skipped_items` hold origins + enums + decisions only, never node content.
 *
 * @property int $id
 * @property string $page_translation_key
 * @property string|null $actor
 * @property string $plan_fingerprint
 * @property string $baseline_before
 * @property string $baseline_after
 * @property int $source_layout_id
 * @property int $applied_count
 * @property int $skipped_count
 * @property array<int, array<string, string>> $applied_items
 * @property array<int, array<string, string>> $skipped_items
 * @property string $executed_at
 */
class LayoutTranslationSyncHistory extends Model
{
    protected $table = 'pb_layout_translation_sync_history';

    protected $fillable = [
        'page_translation_key',
        'actor',
        'plan_fingerprint',
        'baseline_before',
        'baseline_after',
        'source_layout_id',
        'applied_count',
        'skipped_count',
        'applied_items',
        'skipped_items',
        'executed_at',
    ];

    protected $casts = [
        'source_layout_id' => 'integer',
        'applied_count' => 'integer',
        'skipped_count' => 'integer',
        'applied_items' => 'array',
        'skipped_items' => 'array',
    ];
}
