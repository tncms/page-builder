<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Preview;

/**
 * Fills empty, well-known fields of a resource tree with deterministic sample
 * content so previews look real (especially for sparse blocks/sections).
 *
 * Guarantees:
 *  - Deterministic: the same input tree always yields the same output. Sample
 *    values are chosen by a stable hash of the node path + key — never random,
 *    never time-based.
 *  - Non-destructive: only missing or empty-string values are filled. Authored
 *    content (e.g. a Full Page Template's real copy) is never overwritten.
 *  - Display-only: this runs before rendering and is discarded; it never mutates
 *    the stored resource.
 */
final class SampleContentResolver
{
    /** @var array<int, string> */
    private const HEADINGS = [
        'Design that speaks',
        'Build pages faster',
        'Your story, beautifully told',
        'Crafted for clarity',
        'Everything in its right place',
    ];

    /** @var array<int, string> */
    private const PARAGRAPHS = [
        'A concise sentence that demonstrates real content flow and rhythm.',
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor.',
        'Everything you need, and nothing you do not — thoughtfully composed.',
        'Clear typography and generous spacing make the message easy to read.',
    ];

    /** @var array<int, string> */
    private const LABELS = [
        'Get started',
        'Learn more',
        'Explore',
        'See how it works',
    ];

    /** @var array<int, string> */
    private const NAMES = ['Alex Morgan', 'Jordan Lee', 'Sam Rivera', 'Casey Quinn'];

    /** @var array<int, string> */
    private const ROLES = ['Product Designer', 'Founder', 'Engineer', 'Marketing Lead'];

    /** Keys that hold heading-like text. */
    private const HEADING_KEYS = ['title', 'heading', 'question', 'eyebrow'];

    /** Keys that hold body text. */
    private const BODY_KEYS = ['subtitle', 'text', 'description', 'answer', 'quote', 'caption'];

    /** Keys that hold short labels. */
    private const LABEL_KEYS = ['label', 'button_label', 'cta_label'];

    /** Keys that hold a person name. */
    private const NAME_KEYS = ['name', 'author', 'cite'];

    /** Keys that hold a person role. */
    private const ROLE_KEYS = ['role', 'position'];

    /** Keys that hold a URL (filled with a safe in-page anchor). */
    private const URL_KEYS = ['url', 'href', 'link', 'button_url'];

    /** Keys that hold an image URL (filled with a safe relative placeholder). */
    private const IMAGE_KEYS = ['src', 'image', 'background', 'logo', 'avatar', 'poster'];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function apply(array $payload): array
    {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        $out = [];

        foreach ($sections as $i => $section) {
            $out[] = is_array($section) ? $this->fillNode($section, 's'.$i) : $section;
        }

        $payload['sections'] = $out;

        return $payload;
    }

    /**
     * Recursively fill a section/block node and its children.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function fillNode(array $node, string $path): array
    {
        $type = is_string($node['type'] ?? null) ? $node['type'] : '';
        $data = is_array($node['data'] ?? null) ? $node['data'] : [];

        $data = $this->fillData($data, $path.'.'.$type);

        // Nested blocks may live under data.blocks (containers/columns).
        if (isset($data['blocks']) && is_array($data['blocks'])) {
            $data['blocks'] = $this->fillChildren($data['blocks'], $path.'.data');
        }

        $node['data'] = $data;

        // Section-level blocks.
        if (isset($node['blocks']) && is_array($node['blocks'])) {
            $node['blocks'] = $this->fillChildren($node['blocks'], $path);
        }

        return $node;
    }

    /**
     * @param  array<int, mixed>  $children
     * @return array<int, mixed>
     */
    private function fillChildren(array $children, string $path): array
    {
        $out = [];
        foreach ($children as $i => $child) {
            $out[] = is_array($child) ? $this->fillNode($child, $path.'.b'.$i) : $child;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillData(array $data, string $path): array
    {
        foreach ($this->fieldMap() as $key => $kind) {
            if ($this->isEmpty($data[$key] ?? null)) {
                $data[$key] = $this->sampleFor($kind, $path.'.'.$key);
            }
        }

        return $data;
    }

    /**
     * @return array<string, string> key => sample kind
     */
    private function fieldMap(): array
    {
        $map = [];
        foreach (self::HEADING_KEYS as $k) {
            $map[$k] = 'heading';
        }
        foreach (self::BODY_KEYS as $k) {
            $map[$k] = 'body';
        }
        foreach (self::LABEL_KEYS as $k) {
            $map[$k] = 'label';
        }
        foreach (self::NAME_KEYS as $k) {
            $map[$k] = 'name';
        }
        foreach (self::ROLE_KEYS as $k) {
            $map[$k] = 'role';
        }
        foreach (self::URL_KEYS as $k) {
            $map[$k] = 'url';
        }
        foreach (self::IMAGE_KEYS as $k) {
            $map[$k] = 'image';
        }

        return $map;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || (is_array($value) && $value === []);
    }

    private function sampleFor(string $kind, string $seed): string
    {
        return match ($kind) {
            'heading' => $this->pick(self::HEADINGS, $seed),
            'body' => $this->pick(self::PARAGRAPHS, $seed),
            'label' => $this->pick(self::LABELS, $seed),
            'name' => $this->pick(self::NAMES, $seed),
            'role' => $this->pick(self::ROLES, $seed),
            'url' => '#',
            'image' => '/images/page-builder/preview/placeholder-'.($this->index($seed, 4) + 1).'.svg',
            default => '',
        };
    }

    /**
     * @param  array<int, string>  $pool
     */
    private function pick(array $pool, string $seed): string
    {
        return $pool[$this->index($seed, count($pool))];
    }

    private function index(string $seed, int $mod): int
    {
        return $mod > 0 ? (int) (crc32($seed) % $mod) : 0;
    }
}
