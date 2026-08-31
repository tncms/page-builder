<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Preview;

use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * Generation-only preview payloads for the three mounted built-in Template-Picker
 * templates (PB-FREE-LIBRARY-DESIGN-1-F).
 *
 * The built-in templates (`hero-basic`, `feature-grid-basic`, `cta-basic`) are authored
 * as client-side TypeScript {@see \Plugins\PageBuilder resources/editor/templates/builtin}
 * definitions; they are NOT repository DesignResources, so the Runtime Preview engine —
 * which resolves a resource by RID — cannot render them directly. This is the smallest
 * plugin-owned adapter that submits each template's CANONICAL, NORMALIZED payload to the
 * SAME preview renderer ({@see \Plugins\PageBuilder\Support\LayoutRenderer}) so a captured
 * thumbnail depicts exactly what Apply materializes.
 *
 * The payloads mirror the normalized TS definitions verbatim (heading `level` as the
 * canonical `h1..h6` string, button links in the canonical `url` field — the schema the
 * PHP renderer reads). A parity test asserts this PHP source stays byte-equivalent in
 * structure to the TS definitions, so the two never diverge. This class carries NO
 * localized labels and does not affect the templates' identity, apply flow, or the
 * client registry — it exists solely so the offline thumbnail generator can render them.
 */
final class BuiltinTemplatePreviewCatalog
{
    /** Template ids that map to a generation-only preview payload. */
    public const IDS = ['hero-basic', 'feature-grid-basic', 'cta-basic'];

    /**
     * The canonical id-free section trees for every built-in template, keyed by id. This is
     * the shape compared against the authoritative TypeScript `layout.tree.sections` (the
     * parity fixture) so the PHP projection can never silently diverge from Apply's source.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function sectionsById(): array
    {
        $out = [];
        foreach (self::IDS as $id) {
            $payload = $this->payload($id) ?? [];
            $out[$id] = array_map(static function (array $section): array {
                unset($section['id']); // the TS source carries no section id

                return $section;
            }, $payload['sections'] ?? []);
        }

        return $out;
    }

    /**
     * The normalized LayoutPayload for a built-in template id, or null when unknown.
     *
     * @return array<string, mixed>|null
     */
    public function payload(string $id): ?array
    {
        return match ($id) {
            'hero-basic' => $this->heroBasic(),
            'feature-grid-basic' => $this->featureGridBasic(),
            'cta-basic' => $this->ctaBasic(),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function heroBasic(): array
    {
        return $this->document([
            $this->section(['spacing' => ['padding' => ['top' => 64, 'right' => 0, 'bottom' => 64, 'left' => 0]]], [
                $this->block('heading', ['level' => 'h1', 'text' => 'Build something people love']),
                $this->block('text', ['text' => 'A short, benefit-driven subheading that supports the headline.']),
                $this->block('button', ['label' => 'Get started', 'url' => '#']),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function featureGridBasic(): array
    {
        $column = fn (string $title): array => [
            'type' => 'column',
            'data' => [
                'blocks' => [
                    $this->block('heading', ['level' => 'h3', 'text' => $title]),
                    $this->block('text', ['text' => 'A concise description of this feature and why it matters.']),
                ],
            ],
        ];

        return $this->document([
            $this->section(['spacing' => ['padding' => ['top' => 48, 'right' => 0, 'bottom' => 48, 'left' => 0]]], [
                $this->block('container', ['blocks' => [$column('Fast'), $column('Reliable'), $column('Secure')]]),
            ]),
        ]);
    }

    /** @return array<string, mixed> */
    private function ctaBasic(): array
    {
        return $this->document([
            $this->section(['spacing' => ['padding' => ['top' => 56, 'right' => 0, 'bottom' => 56, 'left' => 0]]], [
                $this->block('heading', ['level' => 'h2', 'text' => 'Ready to get started?']),
                $this->block('button', ['label' => 'Create your account', 'url' => '#']),
            ]),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function document(array $sections): array
    {
        return ['schema' => LayoutNormalizer::SCHEMA, 'sections' => $sections];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function section(array $data, array $blocks): array
    {
        return ['type' => 'section', 'id' => null, 'data' => $data, 'blocks' => $blocks];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function block(string $type, array $data): array
    {
        return ['type' => $type, 'data' => $data];
    }
}
