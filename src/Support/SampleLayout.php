<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * A representative sample layout using the Phase 4 library.
 *
 * Serves as a fixture for tests and as a documentation-by-example of the
 * canonical (pagebuilder/01) payload shape: a hero with heading/text/button/
 * image blocks, a two-column row with nested blocks, an FAQ of accordion items,
 * a logo strip, and a testimonial quote. It is data only — no HTML, no assets.
 */
final class SampleLayout
{
    /**
     * The sample layout payload (ready to pass to the renderer/normalizer).
     *
     * @return array{schema: string, sections: array<int, array<string, mixed>>}
     */
    public static function make(): array
    {
        return [
            'schema' => LayoutNormalizer::SCHEMA,
            'sections' => [
                [
                    'type' => 'hero',
                    'id' => 'hero-1',
                    'data' => ['align' => 'center'],
                    'blocks' => [
                        ['type' => 'heading', 'data' => ['text' => 'Build pages, not markup', 'level' => 'h1']],
                        ['type' => 'text', 'data' => ['text' => 'A lightweight, server-rendered page builder.']],
                        ['type' => 'button', 'data' => ['label' => 'Get started', 'url' => '/get-started', 'style' => 'primary']],
                        ['type' => 'image', 'data' => ['src' => '/img/hero.png', 'alt' => 'Product screenshot']],
                    ],
                ],
                [
                    'type' => 'columns',
                    'id' => 'cols-1',
                    'data' => ['gap' => 'md'],
                    'blocks' => [
                        ['type' => 'column', 'data' => [
                            'width' => 'half',
                            'blocks' => [
                                ['type' => 'heading', 'data' => ['text' => 'Fast', 'level' => 'h3']],
                                ['type' => 'text', 'data' => ['text' => 'Rendered on the server.']],
                            ],
                        ]],
                        ['type' => 'column', 'data' => [
                            'width' => 'half',
                            'blocks' => [
                                ['type' => 'heading', 'data' => ['text' => 'Safe', 'level' => 'h3']],
                                ['type' => 'text', 'data' => ['text' => 'Output is escaped by default.']],
                            ],
                        ]],
                    ],
                ],
                [
                    'type' => 'faq',
                    'id' => 'faq-1',
                    'data' => ['heading' => 'Questions'],
                    'blocks' => [
                        ['type' => 'accordion-item', 'data' => ['question' => 'Is it free?', 'answer' => 'Yes.']],
                        ['type' => 'accordion-item', 'data' => ['question' => 'Does it use Vue?', 'answer' => 'Not on the frontend.']],
                    ],
                ],
                [
                    'type' => 'logo-strip',
                    'id' => 'logos-1',
                    'data' => ['heading' => 'Trusted by'],
                    'blocks' => [
                        ['type' => 'logo', 'data' => ['src' => '/img/acme.svg', 'alt' => 'Acme', 'url' => 'https://acme.test']],
                        ['type' => 'logo', 'data' => ['src' => '/img/globex.svg', 'alt' => 'Globex']],
                    ],
                ],
                [
                    'type' => 'testimonial',
                    'id' => 'quote-1',
                    'data' => [],
                    'blocks' => [
                        ['type' => 'quote', 'data' => ['text' => 'It just works.', 'author' => 'A. User', 'role' => 'Developer']],
                    ],
                ],
            ],
        ];
    }
}
