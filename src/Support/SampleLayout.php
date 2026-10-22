<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

/**
 * A representative sample layout using the Phase 4 library.
 *
 * Serves as a fixture for tests and as a documentation-by-example of the
 * canonical (pagebuilder/01) payload shape: a hero with heading/text/button/
 * image blocks, a two-column row with nested blocks, an FAQ of accordion items,
 * a logo strip, a testimonial quote (social proof), and a closing call to action
 * (the governed "CTA last" invariant). It is data only — no HTML, no assets.
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
                    'data' => ['align' => 'center', 'style' => 'surface'],
                    'blocks' => [
                        ['type' => 'heading', 'data' => ['text' => ['en' => 'Build your page, block by block', 'vi' => 'Dựng trang của bạn, từng khối một'], 'level' => 'h1']],
                        ['type' => 'text', 'data' => ['text' => ['en' => 'A lightweight, server-rendered page builder. Everything on this page is editable — replace the words, swap the image, and add or remove sections to make it yours.', 'vi' => 'Một trình dựng trang nhẹ, kết xuất phía máy chủ. Mọi thứ trên trang này đều có thể chỉnh sửa — thay chữ, đổi ảnh, thêm hoặc bớt mục để biến nó thành của bạn.']]],
                        ['type' => 'button', 'data' => ['label' => ['en' => 'Start editing', 'vi' => 'Bắt đầu chỉnh sửa'], 'url' => '#', 'style' => 'primary', 'size' => 'md']],
                        ['type' => 'image', 'data' => ['src' => '/vendor/page-builder/img/placeholders/image.svg', 'alt' => ['en' => 'Replace this with your own image', 'vi' => 'Hãy thay bằng hình ảnh của riêng bạn'], 'caption' => '']],
                    ],
                ],
                [
                    'type' => 'columns',
                    'id' => 'cols-1',
                    'data' => ['gap' => 'lg'],
                    'blocks' => [
                        ['type' => 'column', 'data' => [
                            'width' => 'half',
                            'surface' => 'surface',
                            'blocks' => [
                                ['type' => 'heading', 'data' => ['text' => ['en' => 'Compose visually', 'vi' => 'Dựng trực quan'], 'level' => 'h3']],
                                ['type' => 'text', 'data' => ['text' => ['en' => 'Drag in sections and blocks, then arrange them the way you want — no markup required.', 'vi' => 'Kéo thả các mục và khối, rồi sắp xếp theo ý bạn — không cần viết mã.']]],
                            ],
                        ]],
                        ['type' => 'column', 'data' => [
                            'width' => 'half',
                            'surface' => 'surface',
                            'blocks' => [
                                ['type' => 'heading', 'data' => ['text' => ['en' => 'Safe by default', 'vi' => 'An toàn mặc định'], 'level' => 'h3']],
                                ['type' => 'text', 'data' => ['text' => ['en' => 'Your content is escaped automatically, so pages stay secure as you edit them.', 'vi' => 'Nội dung của bạn được tự động escape, nên trang luôn an toàn khi bạn chỉnh sửa.']]],
                            ],
                        ]],
                    ],
                ],
                [
                    'type' => 'faq',
                    'id' => 'faq-1',
                    'data' => ['heading' => ['en' => 'Good to know', 'vi' => 'Những điều nên biết']],
                    'blocks' => [
                        ['type' => 'accordion-item', 'data' => ['question' => ['en' => 'Can I change everything on this page?', 'vi' => 'Tôi có thể thay đổi mọi thứ trên trang này không?'], 'answer' => ['en' => 'Yes — every block and section is fully editable after you import the page.', 'vi' => 'Có — mọi khối và mục đều có thể chỉnh sửa trọn vẹn sau khi bạn nhập trang.']]],
                        ['type' => 'accordion-item', 'data' => ['question' => ['en' => 'Do I need to write code?', 'vi' => 'Tôi có cần viết mã không?'], 'answer' => ['en' => 'No. Pages are composed from reusable blocks you arrange visually.', 'vi' => 'Không. Trang được tạo nên từ các khối tái dùng mà bạn sắp xếp một cách trực quan.']]],
                    ],
                ],
                [
                    'type' => 'logo-strip',
                    'id' => 'logos-1',
                    'data' => ['heading' => ['en' => 'Add your own logos here', 'vi' => 'Thêm logo của riêng bạn tại đây']],
                    'blocks' => [
                        ['type' => 'logo', 'data' => ['src' => '/vendor/page-builder/img/placeholders/logo.svg', 'alt' => ['en' => 'Logo placeholder', 'vi' => 'Logo mẫu']]],
                        ['type' => 'logo', 'data' => ['src' => '/vendor/page-builder/img/placeholders/logo.svg', 'alt' => ['en' => 'Logo placeholder', 'vi' => 'Logo mẫu']]],
                    ],
                ],
                [
                    'type' => 'testimonial',
                    'id' => 'quote-1',
                    'data' => [],
                    'blocks' => [
                        ['type' => 'quote', 'data' => ['text' => ['en' => 'This is where a customer quote goes. Replace it with your own to add a little social proof.', 'vi' => 'Đây là nơi đặt một trích dẫn khách hàng. Hãy thay bằng của riêng bạn để tăng thêm sự tin cậy.'], 'author' => 'Sample Author', 'role' => ['en' => 'Your title', 'vi' => 'Chức danh của bạn']]],
                    ],
                ],
                [
                    // Closing call to action — the governed "CTA last" invariant. Its own
                    // editable title (h2) / text / primary button; safe placeholder URL.
                    'type' => 'cta',
                    'id' => 'cta-1',
                    'data' => [
                        'align' => 'center',
                        'title' => ['en' => 'Make this page your own', 'vi' => 'Biến trang này thành của bạn'],
                        'text' => ['en' => 'Import this template, then edit every block right in the page builder — no code required.', 'vi' => 'Nhập mẫu này, rồi chỉnh sửa mọi khối ngay trong trình dựng trang — không cần viết mã.'],
                        'button_label' => ['en' => 'Import and start editing', 'vi' => 'Nhập và bắt đầu chỉnh sửa'],
                        'button_url' => '#',
                    ],
                    'blocks' => [],
                ],
            ],
        ];
    }
}
