<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Management;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Plugins\PageBuilder\Management\Exceptions\MetadataValidationException;

/**
 * Applies a validated metadata edit to a "My Designs" record (Phase 26.49,
 * Rules 3/9/10/19/20).
 *
 * ONLY descriptive metadata may change: name, description, category, tags,
 * visibility and version notes. Every system-managed field — RID, source, kind,
 * resourceId, version, author, compatibility, fingerprint, preview, distribution
 * and above all the normalized payload — is copied through untouched. The
 * fingerprint is never recomputed (Rule 10) and the payload is never edited
 * (Rule 9). The method is pure: it returns a NEW record and never mutates its
 * input.
 */
final class MetadataUpdater
{
    /** The only fields a metadata edit may touch (Rule 3). */
    public const EDITABLE = ['name', 'description', 'category', 'tags', 'visibility', 'versionNotes'];

    /** Allowed visibility values — sharing/publishing are reserved (Rule 21). */
    public const VISIBILITIES = ['private', 'unlisted'];

    private const NAME_MAX = 160;

    private const DESCRIPTION_MAX = 2000;

    private const VERSION_NOTES_MAX = 2000;

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $input   only keys present are applied (partial edit)
     * @return array<string, mixed>
     *
     * @throws MetadataValidationException
     */
    public function apply(array $record, array $input): array
    {
        $issues = [];
        $next = $record;

        if (array_key_exists('name', $input)) {
            $name = is_string($input['name']) ? trim($input['name']) : '';
            if ($name === '') {
                $issues[] = ['code' => 'name_required', 'message' => 'A design name is required.'];
            } elseif (mb_strlen($name) > self::NAME_MAX) {
                $issues[] = ['code' => 'name_too_long', 'message' => 'The design name is too long.'];
            } else {
                $next['name'] = $name;
            }
        }

        if (array_key_exists('description', $input)) {
            $next['description'] = $this->boundedText($input['description'], self::DESCRIPTION_MAX, 'description', $issues);
        }

        if (array_key_exists('category', $input)) {
            $slug = is_string($input['category']) ? Str::slug($input['category']) : '';
            $next['category'] = $slug !== '' ? $slug : 'general';
        }

        if (array_key_exists('tags', $input)) {
            $next['tags'] = $this->tags($input['tags']);
        }

        if (array_key_exists('visibility', $input)) {
            $visibility = is_string($input['visibility']) ? strtolower(trim($input['visibility'])) : '';
            if (! in_array($visibility, self::VISIBILITIES, true)) {
                $issues[] = ['code' => 'visibility_invalid', 'message' => 'The visibility value is not allowed.'];
            } else {
                $next['visibility'] = $visibility;
            }
        }

        if (array_key_exists('versionNotes', $input)) {
            $next['versionNotes'] = $this->boundedText($input['versionNotes'], self::VERSION_NOTES_MAX, 'versionNotes', $issues);
        }

        if ($issues !== []) {
            throw new MetadataValidationException($issues);
        }

        $next['updatedAt'] = Carbon::now()->toIso8601String();

        return $next;
    }

    /**
     * The editable keys whose value changed between two records (drives the event).
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, string>
     */
    public function changedFields(array $before, array $after): array
    {
        $changed = [];
        foreach (self::EDITABLE as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * @param  array<int, array{code: string, message: string}>  $issues
     */
    private function boundedText(mixed $value, int $max, string $field, array &$issues): ?string
    {
        $text = is_string($value) ? trim($value) : '';
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $max) {
            $issues[] = ['code' => $field.'_too_long', 'message' => 'The '.$field.' is too long.'];

            return null;
        }

        return $text;
    }

    /**
     * @return array<int, string>
     */
    private function tags(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $tags = [];
        foreach ($value as $tag) {
            if (is_string($tag) && ($slug = Str::slug($tag)) !== '') {
                $tags[$slug] = true;
            }
        }

        return array_keys($tags);
    }
}
