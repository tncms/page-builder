<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\TimelineInfrastructure;

use Plugins\PageBuilder\LayoutTranslation\Identity\PageTranslationKey;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Entry\TimelineEntry;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Projection\TimelineFilter;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Projection\TimelineProjection;
use Plugins\PageBuilder\LayoutTranslation\Timeline\Projection\TimelineProjectionBuilder;

/**
 * Read-only list querying over the timeline (LT-9C, per INV-LT-9C-01).
 *
 * Layer: Translation (timeline application). It builds the timeline through the {@see
 * TimelineApplicationService} and delegates all DOMAIN filtering to the runtime's immutable projections
 * ({@see TimelineProjectionBuilder} / {@see TimelineFilter} / gaps). Sorting and pagination are pure view
 * mechanics applied over the resulting immutable projection — they filter no domain data. It mutates
 * nothing and returns an immutable {@see TimelineListResult}.
 */
final class TimelineQueryService
{
    private const SORTABLE = ['executed_at', 'id', 'actor', 'applied_count', 'skipped_count'];

    public function __construct(
        private readonly TimelineApplicationService $app,
    ) {}

    public function list(
        PageTranslationKey $target,
        TimelineFilter $filter,
        bool $gapsOnly = false,
        string $sort = 'executed_at',
        string $direction = 'desc',
        int $page = 1,
        int $perPage = 20,
    ): TimelineListResult {
        $timeline = $this->app->timeline($target);
        $builder = new TimelineProjectionBuilder($timeline);

        // DOMAIN filtering is always a runtime projection.
        $projection = $gapsOnly ? $builder->gapsOnly() : $builder->filter($filter);

        $gapIds = $this->gapIds($timeline->chain()->gaps());
        $entries = $this->sort($projection->entries(), $sort, $direction);

        $total = count($entries);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $slice = array_slice($entries, ($page - 1) * $perPage, $perPage);

        $items = array_map(
            static fn (TimelineEntry $e): array => $e->toArray() + ['has_gap' => isset($gapIds[$e->id->value])],
            $slice,
        );

        return new TimelineListResult($items, $total, $page, $perPage);
    }

    public function projection(PageTranslationKey $target, TimelineFilter $filter): TimelineProjection
    {
        return (new TimelineProjectionBuilder($this->app->timeline($target)))->filter($filter);
    }

    /**
     * @param  array<int, \Plugins\PageBuilder\LayoutTranslation\Timeline\Chain\TimelineGap>  $gaps
     * @return array<int, true>
     */
    private function gapIds(array $gaps): array
    {
        $ids = [];
        foreach ($gaps as $gap) {
            $ids[$gap->afterEntryId->value] = true;
            $ids[$gap->beforeEntryId->value] = true;
        }

        return $ids;
    }

    /**
     * Presentation-only ordering over the immutable projection (never a domain filter).
     *
     * @param  array<int, TimelineEntry>  $entries
     * @return array<int, TimelineEntry>
     */
    private function sort(array $entries, string $sort, string $direction): array
    {
        $field = in_array($sort, self::SORTABLE, true) ? $sort : 'executed_at';
        $sign = strtolower($direction) === 'asc' ? 1 : -1;

        usort($entries, static function (TimelineEntry $a, TimelineEntry $b) use ($field, $sign): int {
            return $sign * match ($field) {
                'id' => $a->id->value <=> $b->id->value,
                'actor' => strcmp((string) $a->actor, (string) $b->actor),
                'applied_count' => $a->appliedCount <=> $b->appliedCount,
                'skipped_count' => $a->skippedCount <=> $b->skippedCount,
                default => strcmp($a->executedAt, $b->executedAt),
            };
        });

        return $entries;
    }
}
