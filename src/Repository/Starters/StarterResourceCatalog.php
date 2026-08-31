<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository\Starters;

use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;
use Plugins\PageBuilder\Support\LayoutNormalizer;

/**
 * The FREE starter Design Resource catalog (Phase 26.46).
 *
 * Real, renderable resources — curated pages and sections composed ENTIRELY from
 * registered built-in section/block types (see {@see \Plugins\PageBuilder\Support\DefaultLibrary}).
 * Nothing here forks the model: every entry is a plain {@see DesignResource} with a
 * renderable, id-free LayoutPayload, so the Runtime Preview Engine and Full Page
 * Import consume them directly.
 *
 * Content is deterministic, generic placeholder copy — no business identity, no
 * hardcoded colors (visual variety comes from section `style` presets), and every
 * asset is a safe root-relative URL. Users edit everything after import.
 */
final class StarterResourceCatalog
{
    public const SOURCE = 'builtin';
    private const VERSION = '1.0.0';

    /**
     * Vietnamese catalog metadata keyed by resourceId (PB-FREE-LIBRARY-DESIGN-1-F).
     *
     * The invariant English name/description stay inline at each resource (single
     * source); this map is the VI-only companion. The two are merged into the
     * resource's `localized` maps ({@see localizedMetadata()}) and resolved by Admin
     * UI locale at the catalog presentation boundary ({@see \Plugins\PageBuilder\Support\ResourceMetadataLocalizer}).
     * Machine fields (rid/kind/category/tags/version/payload) stay locale-invariant.
     *
     * @var array<string, array{name: string, description: string}>
     */
    private const VI_METADATA = [
        // Pages
        'company-home' => [
            'name' => 'Trang chủ doanh nghiệp',
            'description' => 'Trang chủ doanh nghiệp đáng tin cậy: hero giá trị, dải thương hiệu tin dùng, dịch vụ, cách tiếp cận, minh chứng, nhận xét và lời kêu gọi hành động khép lại.',
        ],
        'agency-home' => [
            'name' => 'Trang chủ agency',
            'description' => 'Trang agency sáng tạo năng động: hero theo kết quả, dự án tiêu biểu, dịch vụ, minh chứng, nhận xét và lời mời hợp tác dự án mạnh mẽ.',
        ],
        'saas-landing' => [
            'name' => 'Trang đích SaaS',
            'description' => 'Trang SaaS hướng sản phẩm: hero lời hứa, ảnh sản phẩm, lợi ích, quy trình, nhận xét, bảng giá, câu hỏi thường gặp và CTA đăng ký.',
        ],
        'portfolio' => [
            'name' => 'Portfolio cá nhân',
            'description' => 'Portfolio cá nhân lấy tác phẩm làm trọng tâm: hero định vị, thư viện dự án có chú thích, kỹ năng, phần giới thiệu và CTA liên hệ.',
        ],
        'blog-home' => [
            'name' => 'Trang chủ blog',
            'description' => 'Trang blog kiểu biên tập: bài viết nổi bật, khám phá chủ đề, bài viết mẫu, ghi chú giới thiệu và CTA đăng ký tĩnh.',
        ],
        // Sections
        'hero-modern' => [
            'name' => 'Hero hiện đại',
            'description' => 'Khu hero nền tối, nổi bật với tiêu đề, dòng phụ và một hành động chính.',
        ],
        'hero-company' => [
            'name' => 'Hero doanh nghiệp',
            'description' => 'Khu hero doanh nghiệp điềm đạm trên nền bề mặt nhẹ.',
        ],
        'hero-saas' => [
            'name' => 'Hero SaaS',
            'description' => 'Khu hero sản phẩm kết hợp tiêu đề với ảnh chụp giao diện.',
        ],
        'feature-grid' => [
            'name' => 'Lưới tính năng',
            'description' => 'Lưới ba cột gồm các điểm nổi bật tính năng ngắn gọn.',
        ],
        'feature-cards' => [
            'name' => 'Thẻ tính năng',
            'description' => 'Một hàng thẻ tính năng, mỗi thẻ có tiêu đề, mô tả và hành động.',
        ],
        'testimonials' => [
            'name' => 'Nhận xét khách hàng',
            'description' => 'Ba nhận xét của khách hàng trình bày thành các thẻ đều nhau, gọn gàng.',
        ],
        'logo-strip' => [
            'name' => 'Dải logo',
            'description' => 'Một hàng logo khách hàng hoặc đối tác dưới một tiêu đề ngắn, tạo sự tin cậy.',
        ],
        'cta-banner' => [
            'name' => 'Banner kêu gọi hành động',
            'description' => 'Dải kêu gọi hành động tập trung với tiêu đề và nút bấm.',
        ],
        'pricing' => [
            'name' => 'Bảng giá',
            'description' => 'Một hàng bảng giá ba bậc với gói khuyến nghị được làm nổi bật.',
        ],
    ];

    /** @var array<string, DesignResource>|null */
    private ?array $byRid = null;

    /**
     * @return array<int, DesignResource>
     */
    public function all(): array
    {
        return [...$this->pages(), ...$this->sections()];
    }

    public function find(string $rid): ?DesignResource
    {
        if ($this->byRid === null) {
            $this->byRid = [];
            foreach ($this->all() as $resource) {
                $this->byRid[$resource->rid] = $resource;
            }
        }

        return $this->byRid[$rid] ?? null;
    }

    // ---- Pages ---------------------------------------------------------------

    /**
     * @return array<int, DesignResource>
     */
    public function pages(): array
    {
        return [
            $this->page('company-home', 'Company Homepage', 'business', ['business', 'corporate', 'homepage'],
                'A credible corporate homepage: value hero, trust strip, services, approach, proof, testimonials and a closing call to action.', [
                    $this->pageHeroNode(
                        'Software that keeps your business moving',
                        'We help established teams modernise their web platforms with reliable, maintainable software — delivered on a schedule you can plan around.',
                        'Get started', 'Talk to sales', 'surface'),
                    $this->pageLogoStripNode('Trusted by operations teams at growing companies'),
                    $this->introBand('What we do', 'Three focused services that cover the whole lifecycle of a modern web platform.'),
                    $this->cardsNode([
                        ['Consulting', 'We turn your goals into a pragmatic technical plan your team can act on with confidence.'],
                        ['Implementation', 'We build accessible, server-rendered platforms that stay easy to maintain as you grow.'],
                        ['Ongoing support', 'We stay on to keep everything fast, secure and up to date — no surprises.'],
                    ], 'surface', 'lg', '33'),
                    $this->introBand('Why teams choose us', 'A calm, transparent way of working built around your people.'),
                    $this->valueStripNode([
                        ['Clear scope', 'Fixed, well-defined phases.'],
                        ['Honest pricing', 'No hidden line items.'],
                        ['Accessible by default', 'WCAG-minded from day one.'],
                        ['Yours to own', 'Documented, handover-ready.'],
                    ]),
                    $this->pageTestimonialsNode([
                        ['They understood our constraints and delivered exactly what we needed — on time.', 'Priya Anand', 'Operations Director'],
                        ['A rare partner that leaves you with something maintainable, not a black box.', 'Marcus Bell', 'CTO'],
                    ]),
                    $this->ctaNode('Ready to modernise your platform?', 'Book a short, no-pressure call and we will show you what is possible.', 'Get started'),
                ]),
            $this->page('agency-home', 'Agency Homepage', 'agency', ['agency', 'studio', 'creative'],
                'A dynamic creative-agency page: outcome hero, selected work, services, proof, testimonials and a strong project enquiry.', [
                    $this->pageHeroNode(
                        'We turn ambitious ideas into work that performs',
                        'A creative studio for teams who want more than a template — brand, product and web, shipped with craft.',
                        'See our work', 'Start a project', 'dark'),
                    $this->introBand('Selected work', 'A small sample of recent projects across brand, product and web.'),
                    $this->cardsNode([
                        ['Northwind rebrand', 'A complete visual identity and marketing site for a logistics platform.', 'View case'],
                        ['Lumen product UI', 'Design system and app interface for a fast-growing analytics tool.', 'View case'],
                        ['Brightpath campaign', 'Launch site and motion work for a seasonal product campaign.', 'View case'],
                    ], 'card', 'lg', '33'),
                    $this->introBand('How we work', 'A focused process that keeps ideas sharp and delivery predictable.'),
                    $this->valueStripNode([
                        ['01 · Discover', 'Research and positioning.'],
                        ['02 · Design', 'Identity and interface.'],
                        ['03 · Build', 'Accessible, production-ready.'],
                    ]),
                    $this->pageLogoStripNode('A few of the teams we have partnered with'),
                    $this->pageTestimonialsNode([
                        ['They gave our brand a point of view. Sales noticed within the first month.', 'Sofia Marin', 'Founder'],
                        ['Genuinely collaborative, and the craft shows in every detail.', 'Daniel Cho', 'Head of Marketing'],
                    ]),
                    $this->ctaNode('Have a project in mind?', 'Tell us what you are building and we will help you ship it beautifully.', 'Start a project'),
                ]),
            $this->page('saas-landing', 'SaaS Landing', 'saas', ['saas', 'product', 'landing'],
                'A product-led SaaS page: promise hero, product media, benefits, workflow, testimonials, pricing, FAQ and a signup CTA.', [
                    $this->pageHeroNode(
                        'Everything your team needs to plan, build and ship',
                        'One shared workspace that replaces the scattered tools slowing your team down — so work moves in one place.',
                        'Start free', 'Book a demo', 'plain'),
                    $this->mediaBandNode('Product interface preview', 'A quick look at the workspace'),
                    $this->introBand('Built for the way teams actually work', 'Fewer tabs, less busywork, more shipped.'),
                    $this->cardsNode([
                        ['Instant loads', 'Server-rendered pages mean no spinner and no waiting around.'],
                        ['One shared space', 'Plans, docs and updates live together, visible to everyone.'],
                        ['Adapts to you', 'Compose your own views from reusable, editable blocks.'],
                    ], 'surface', 'md', '33'),
                    $this->introBand('From idea to shipped in three steps', 'A simple flow your whole team can follow.'),
                    $this->valueStripNode([
                        ['1 · Plan', 'Capture the work in one place.'],
                        ['2 · Build', 'Collaborate without the handoffs.'],
                        ['3 · Ship', 'Publish and share in a click.'],
                    ]),
                    $this->pageTestimonialsNode([
                        ['We consolidated three subscriptions into one and the team actually prefers it.', 'Alex Rivera', 'Product Lead'],
                        ['Onboarding took an afternoon. That never happens.', 'Jordan Lee', 'Founder'],
                    ]),
                    $this->introBand('Simple, predictable pricing', 'Start free. Upgrade only when it pays for itself.'),
                    $this->pricingCardsNode(),
                    $this->faqNode(),
                    $this->ctaNode('Start building today', 'Free to try, no card required. Upgrade whenever you are ready.', 'Start free'),
                ]),
            $this->page('portfolio', 'Portfolio', 'portfolio', ['portfolio', 'personal', 'showcase'],
                'A work-forward personal portfolio: positioning hero, captioned project gallery, skills, an about section and a contact CTA.', [
                    $this->pageHeroNode(
                        'Designer and developer crafting calm digital products',
                        'I help founders and small teams turn rough ideas into polished, accessible products — end to end.',
                        'View work', 'Get in touch', 'surface'),
                    $this->introBand('Selected work', 'A few recent projects. Each links to a fuller case study you can edit.'),
                    $this->galleryNode([
                        ['Fieldnote', 'Branding and marketing site for a research tool.'],
                        ['Cadence', 'Product design for a scheduling app.'],
                        ['Harbor', 'Design system for a fintech dashboard.'],
                    ]),
                    $this->introBand('What I do', 'A compact set of skills, applied end to end.'),
                    $this->valueStripNode([
                        ['Product design', 'Flows, UI and design systems.'],
                        ['Front-end', 'Accessible, semantic markup.'],
                        ['Brand', 'Identity and art direction.'],
                    ]),
                    $this->pageTestimonialsNode([
                        ['A genuine collaborator — thoughtful, fast, and easy to work with from brief to launch.', 'Elena Ross', 'Founder, Fieldnote'],
                    ]),
                    $this->aboutNode(
                        'About',
                        'I have spent the last decade helping teams ship thoughtful software. I care about clarity, accessibility and details that hold up over time. When I am not designing, I am usually writing about the craft.'),
                    $this->ctaNode('Let’s work together', 'Available for a small number of freelance and collaboration projects each quarter.', 'Get in touch'),
                ]),
            $this->page('blog-home', 'Blog Home', 'blog', ['blog', 'content', 'articles'],
                'An editorial blog landing page: featured story, topic discovery, sample articles, an about note and a static subscribe CTA.', [
                    $this->pageHeroNode(
                        'Field notes on building for the web',
                        'Practical writing on design, front-end and shipping product — a couple of new pieces each month.',
                        'Read the latest', 'Browse archive', 'surface'),
                    $this->mediaBandNode('Featured article illustration', 'This week — Designing for the calm middle of a page'),
                    $this->introBand('Browse by topic', 'Start with the themes we write about most.'),
                    $this->cardsNode([
                        ['Design', 'Interface, systems and the decisions behind them.'],
                        ['Engineering', 'Server-rendered patterns and pragmatic front-end.'],
                        ['Craft', 'Process, taste and the details that add up.'],
                    ], 'surface', 'md', '33'),
                    $this->introBand('Recent articles', 'A few sample posts — edit these to feature your own writing.'),
                    $this->galleryNode([
                        ['Designing for the middle', 'Why the calm parts of a page matter as much as the loud ones.'],
                        ['Rendering on the server', 'A gentle case for HTML-first front-ends in 2026.'],
                        ['Editing without fear', 'How reusable blocks keep a growing site consistent.'],
                    ]),
                    $this->aboutNode(
                        'About this blog',
                        'A small publication from a team that builds websites for a living. No trackers, no noise — just useful writing you can actually apply.'),
                    $this->ctaNode('Never miss a post', 'Add your email to get new articles when they publish. (Sample form — connect your own provider after import.)', 'Subscribe'),
                ]),
        ];
    }

    // ---- Phase-E page-private builders (PB-FREE-LIBRARY-DESIGN-1-E) -----------
    // Dedicated to the five Starter PAGES so a page redesign NEVER edits a helper
    // body shared with an out-of-scope curated SECTION. They compose ONLY
    // registered section/block types with Phase-C tokens and Phase-D surface/card
    // presets; no hardcoded colour, radius, shadow, raw CSS or class is emitted.

    /**
     * A page hero with a single primary CTA and, optionally, a secondary (outline)
     * CTA and a supporting image — both rendered as trusted hero child blocks. The
     * hero title is the page's single <h1> (see hero.blade).
     */
    private function pageHeroNode(string $title, string $subtitle, string $primaryLabel, ?string $secondaryLabel, string $style, ?string $image = null, ?string $imageAlt = null): array
    {
        $blocks = [];
        if ($secondaryLabel !== null && $secondaryLabel !== '') {
            $blocks[] = $this->buttonBlock($secondaryLabel, '#', 'outline');
        }
        if ($image !== null) {
            $blocks[] = $this->imageBlock($image, $imageAlt ?? '');
        }

        return $this->sectionNode('hero', [
            'align' => 'center',
            'style' => $style,
            'width' => 'normal',
            'title' => $title,
            'subtitle' => $subtitle,
            'button_label' => $primaryLabel,
            'button_url' => '#',
        ], $blocks);
    }

    /** A centered section-intro band: an <h2> heading plus a short lead paragraph. */
    private function introBand(string $heading, string $text): array
    {
        return $this->sectionNode('section', ['width' => 'normal', 'padding' => 'md', 'background' => ''], [
            $this->headingBlock($heading, 'h2'),
            $this->textBlock($text),
        ]);
    }

    /**
     * A row of content cards (heading + text, optional action) on the token-based
     * surface/card preset — the page-owned counterpart of the curated section
     * helpers, kept page-private so the sections stay byte-equivalent.
     *
     * @param  array<int, array{0: string, 1: string, 2?: string}>  $cards
     */
    private function cardsNode(array $cards, string $surface, string $gap, string $width): array
    {
        $columns = [];
        foreach ($cards as $card) {
            $blocks = [
                $this->headingBlock($card[0], 'h3'),
                $this->textBlock($card[1]),
            ];
            if (! empty($card[2])) {
                $blocks[] = $this->buttonBlock($card[2], '#', 'link');
            }
            $columns[] = $this->columnBlock($width, $blocks, $surface);
        }

        return $this->sectionNode('columns', ['gap' => $gap], $columns);
    }

    /**
     * A compact, quiet value strip: equal columns with a short <h3> label and one
     * line of text, no surface card — a deliberate change of rhythm from the card
     * rows.
     *
     * @param  array<int, array{0: string, 1: string}>  $values
     */
    private function valueStripNode(array $values): array
    {
        $width = count($values) >= 4 ? '25' : '33';
        $columns = [];
        foreach ($values as [$label, $text]) {
            $columns[] = $this->columnBlock($width, [
                $this->headingBlock($label, 'h3'),
                $this->textBlock($text),
            ]);
        }

        return $this->sectionNode('columns', ['gap' => 'md'], $columns);
    }

    /**
     * A captioned media gallery (image + heading + caption cards) for work/article
     * showcases — every image is a safe, locally packaged placeholder with alt text.
     *
     * @param  array<int, array{0: string, 1: string}>  $items
     */
    private function galleryNode(array $items): array
    {
        $columns = [];
        foreach ($items as [$title, $caption]) {
            $columns[] = $this->columnBlock('33', [
                $this->imageBlock(StarterAssets::IMAGE_PLACEHOLDER, $title),
                $this->headingBlock($title, 'h3'),
                $this->textBlock($caption),
            ], 'surface');
        }

        return $this->sectionNode('columns', ['gap' => 'lg'], $columns);
    }

    /** A single captioned media band (image-section) on its own full-width row. */
    private function mediaBandNode(string $alt, string $caption): array
    {
        return $this->sectionNode('image-section', [
            'align' => 'center',
            'image_url' => StarterAssets::IMAGE_PLACEHOLDER,
            'alt' => $alt,
            'caption' => $caption,
        ], [
            $this->imageBlock(StarterAssets::IMAGE_PLACEHOLDER, $alt),
        ]);
    }

    /** A calm about/editorial band: an <h2> plus a longer paragraph. */
    private function aboutNode(string $heading, string $text): array
    {
        return $this->sectionNode('section', ['width' => 'narrow', 'padding' => 'lg', 'background' => ''], [
            $this->headingBlock($heading, 'h2'),
            $this->textBlock($text),
        ]);
    }

    /**
     * A page-owned testimonial band (distinct quotes per page). Page-private so it
     * never disturbs the curated `testimonials` section ({@see testimonialCardsNode}).
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $quotes
     */
    private function pageTestimonialsNode(array $quotes): array
    {
        $blocks = [];
        foreach ($quotes as [$quote, $author, $role]) {
            $blocks[] = $this->quoteBlock($quote, $author, $role);
        }

        return $this->sectionNode('testimonial', [], $blocks);
    }

    /** A page-owned logo strip (page-private; the curated section keeps its own). */
    private function pageLogoStripNode(string $heading): array
    {
        return $this->sectionNode('logo-strip', ['heading' => $heading], [
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Acme'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Globex'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Initech'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Umbrella'),
        ]);
    }

    // ---- Sections ------------------------------------------------------------

    /**
     * @return array<int, DesignResource>
     */
    public function sections(): array
    {
        return [
            $this->section('hero-modern', 'Modern Hero', 'hero', ['hero', 'dark', 'modern'],
                'A bold, dark hero with a headline, supporting line and primary action.',
                $this->heroNode('Design that works everywhere', 'A modern hero band with a strong headline and a single, clear call to action.', 'Get started', '#', 'dark')),
            $this->section('hero-company', 'Company Hero', 'hero', ['hero', 'business', 'surface'],
                'A calm corporate hero on a soft surface background.',
                $this->heroNode('Build better products faster', 'A confident corporate hero that introduces your company and its promise.', 'Learn more', '#', 'surface')),
            $this->section('hero-saas', 'SaaS Hero', 'hero', ['hero', 'saas', 'product'],
                'A product hero pairing a headline with a screenshot.',
                $this->heroNode('Everything you need to launch', 'A product-led hero with a headline, subtitle, action and a supporting image.', 'Start free', '#', 'plain', StarterAssets::IMAGE_PLACEHOLDER, 'Product screenshot')),
            $this->section('feature-grid', 'Feature Grid', 'features', ['features', 'grid', 'columns'],
                'A three-column grid of concise feature highlights.',
                $this->featureGridNode([
                    ['Fast', 'Rendered on the server for speed.'],
                    ['Flexible', 'Compose pages from reusable blocks.'],
                    ['Safe', 'Output is escaped by default.'],
                ])),
            $this->section('feature-cards', 'Feature Cards', 'features', ['features', 'cards', 'columns'],
                'A row of feature cards, each with a heading, text and action.',
                $this->featureCardsNode([
                    ['Strategy', 'Positioning and research that sets direction.', 'Learn more'],
                    ['Design', 'Interfaces with real character.', 'Learn more'],
                    ['Build', 'Fast, accessible delivery.', 'Learn more'],
                ])),
            $this->section('testimonials', 'Testimonials', 'social-proof', ['testimonials', 'quotes', 'social-proof'],
                'Three customer testimonials laid out as clean, equal cards.',
                $this->testimonialCardsNode()),
            $this->section('logo-strip', 'Logo Strip', 'social-proof', ['logos', 'brands', 'social-proof'],
                'A row of client or partner logos under a short, reassuring heading.',
                $this->logoStripSectionNode()),
            $this->section('cta-banner', 'CTA Banner', 'cta', ['cta', 'conversion', 'banner'],
                'A focused call-to-action band with a headline and button.',
                $this->ctaNode('Ready to get started?', 'Create your first page in minutes — no code required.', 'Get started')),
            $this->section('pricing', 'Pricing', 'conversion', ['pricing', 'plans', 'conversion'],
                'A three-tier pricing row with a highlighted recommended plan.',
                $this->pricingCardsNode()),
        ];
    }

    // ---- Resource builders ---------------------------------------------------

    /**
     * @param  array<int, string>  $tags
     * @param  array<int, array<string, mixed>>  $sectionNodes
     */
    private function page(string $id, string $name, string $category, array $tags, string $description, array $sectionNodes): DesignResource
    {
        return new DesignResource(
            rid: self::SOURCE.':'.Rid::KIND_PAGE.':'.$id,
            source: self::SOURCE,
            kind: Rid::KIND_PAGE,
            resourceId: $id,
            version: self::VERSION,
            name: $name,
            category: $category,
            tags: $tags,
            payload: $this->document($sectionNodes),
            description: $description,
            preview: $this->previewFor($id, Rid::KIND_PAGE),
            compatibility: ['schemaVersion' => '1'],
            distribution: ['entitlement' => 'free'],
            localized: $this->localizedMetadata($id, $name, $description),
        );
    }

    /**
     * @param  array<int, string>  $tags
     * @param  array<string, mixed>  $sectionNode
     */
    private function section(string $id, string $name, string $category, array $tags, string $description, array $sectionNode): DesignResource
    {
        return new DesignResource(
            rid: self::SOURCE.':'.Rid::KIND_SECTION.':'.$id,
            source: self::SOURCE,
            kind: Rid::KIND_SECTION,
            resourceId: $id,
            version: self::VERSION,
            name: $name,
            category: $category,
            tags: $tags,
            payload: $this->document([$sectionNode]),
            description: $description,
            preview: $this->previewFor($id, Rid::KIND_SECTION),
            compatibility: ['schemaVersion' => '1'],
            distribution: ['entitlement' => 'free'],
            localized: $this->localizedMetadata($id, $name, $description),
        );
    }

    /**
     * Build the resource's per-field locale maps (PB-FREE-LIBRARY-DESIGN-1-F). English
     * is the inline invariant (single source); Vietnamese comes from {@see VI_METADATA}.
     * Both locales are set explicitly so a visible resource never depends on EN fallback
     * when the Admin UI is Vietnamese. Only the human presentation fields are localized.
     *
     * @return array{name: array<string, string>, description: array<string, string>}
     */
    private function localizedMetadata(string $id, string $name, string $description): array
    {
        $vi = self::VI_METADATA[$id] ?? null;

        return [
            'name' => ['en' => $name, 'vi' => $vi['name'] ?? $name],
            'description' => ['en' => $description, 'vi' => $vi['description'] ?? $description],
        ];
    }

    /**
     * The catalog preview descriptor (PB-FREE-LIBRARY-DESIGN-1-F). Points the card at
     * the real, deterministically-generated thumbnail shipped for this resource. The
     * former `sampleContent` flag is gone: the catalog thumbnail is now derived from the
     * resource's canonical payload, so Preview never depicts content absent from Apply.
     *
     * @return array<string, mixed>
     */
    private function previewFor(string $id, string $kind): array
    {
        return [
            'device' => 'desktop',
            'thumbnail' => StarterAssets::thumbnailUrl($kind, $id),
        ];
    }

    // ---- Layout node builders (registered types only) ------------------------

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{schema: string, sections: array<int, array<string, mixed>>}
     */
    private function document(array $sections): array
    {
        return ['schema' => LayoutNormalizer::SCHEMA, 'sections' => $sections];
    }

    /**
     * @return array<string, mixed>
     */
    private function heroNode(string $title, string $subtitle, string $buttonLabel, string $buttonUrl, string $style = 'plain', ?string $image = null, ?string $imageAlt = null): array
    {
        $blocks = [];
        if ($image !== null) {
            $blocks[] = $this->imageBlock($image, $imageAlt ?? '');
        }

        return $this->sectionNode('hero', [
            'align' => 'center',
            'style' => $style,
            'width' => 'normal',
            'title' => $title,
            'subtitle' => $subtitle,
            'button_label' => $buttonLabel,
            'button_url' => $buttonUrl,
        ], $blocks);
    }

    private function ctaNode(string $title, string $text, string $buttonLabel, string $buttonUrl = '#'): array
    {
        return $this->sectionNode('cta', [
            'align' => 'center',
            'title' => $title,
            'text' => $text,
            'button_label' => $buttonLabel,
            'button_url' => $buttonUrl,
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $features
     */
    private function featureGridNode(array $features): array
    {
        $columns = [];
        foreach ($features as [$title, $text]) {
            $columns[] = $this->columnBlock('33', [
                $this->headingBlock($title, 'h3'),
                $this->textBlock($text),
            ]);
        }

        return $this->sectionNode('columns', ['gap' => 'md'], $columns);
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $cards
     */
    private function featureCardsNode(array $cards): array
    {
        $columns = [];
        foreach ($cards as [$title, $text, $action]) {
            $columns[] = $this->columnBlock('33', [
                $this->headingBlock($title, 'h3'),
                $this->textBlock($text),
                $this->buttonBlock($action, '#', 'outline'),
            ]);
        }

        return $this->sectionNode('columns', ['gap' => 'lg'], $columns);
    }

    private function pricingNode(): array
    {
        $tiers = [
            ['Starter', 'For small sites getting off the ground.', 'Choose Starter'],
            ['Pro', 'For growing teams that need more room.', 'Choose Pro'],
            ['Scale', 'For large organisations with advanced needs.', 'Contact sales'],
        ];

        $columns = [];
        foreach ($tiers as [$name, $text, $action]) {
            $columns[] = $this->columnBlock('33', [
                $this->headingBlock($name, 'h3'),
                $this->textBlock($text),
                $this->buttonBlock($action, '#', 'primary'),
            ]);
        }

        return $this->sectionNode('columns', ['gap' => 'md'], $columns);
    }

    private function testimonialsNode(): array
    {
        return $this->sectionNode('testimonial', [], [
            $this->quoteBlock('This made our redesign effortless.', 'Alex Rivera', 'Product Lead'),
            $this->quoteBlock('We shipped in days, not weeks.', 'Jordan Lee', 'Founder'),
        ]);
    }

    private function logoStripNode(string $heading): array
    {
        return $this->sectionNode('logo-strip', ['heading' => $heading], [
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Partner one'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Partner two'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Partner three'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Partner four'),
        ]);
    }

    private function faqNode(): array
    {
        return $this->sectionNode('faq', ['heading' => 'Frequently asked questions'], [
            $this->block('accordion-item', ['question' => 'Is there a free plan?', 'answer' => 'Yes — you can start for free and upgrade later.']),
            $this->block('accordion-item', ['question' => 'Can I edit everything after import?', 'answer' => 'Absolutely. Every block and section is fully editable.']),
            $this->block('accordion-item', ['question' => 'Do I need to write code?', 'answer' => 'No. Pages are composed from reusable blocks.']),
        ]);
    }

    // ---- Batch-D curated section nodes (PB-FREE-LIBRARY-DESIGN-1-D) -----------
    // These are dedicated to the curated SECTIONS. The starter PAGES keep their
    // own shared nodes ({@see testimonialsNode()}/{@see logoStripNode()}/
    // {@see pricingNode()}) unchanged, so Batch-E pages render byte-equivalently.

    /**
     * Three equal customer testimonials as clean surface cards (columns + the
     * token-based `surface` preset), replacing the single stacked pull-quote.
     */
    private function testimonialCardsNode(): array
    {
        $card = fn (string $quote, string $author, string $role): array => $this->columnBlock('33', [
            $this->quoteBlock($quote, $author, $role),
        ], 'surface');

        return $this->sectionNode('columns', ['gap' => 'lg'], [
            $card('This made our redesign effortless — we launched a new marketing site in a week.', 'Alex Rivera', 'Product Lead, Lumen'),
            $card('The block library keeps everything consistent. Non-designers still ship on-brand pages.', 'Jordan Lee', 'Founder, Brightpath'),
            $card('Routine page updates no longer wait on engineering. It paid for itself in a month.', 'Sam Devi', 'Head of Growth, Northwind'),
        ]);
    }

    /** A fuller, reassuring logo strip for the curated section (five marks). */
    private function logoStripSectionNode(): array
    {
        return $this->sectionNode('logo-strip', ['heading' => 'Trusted by teams at fast-growing companies'], [
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Acme'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Globex'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Umbrella'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Initech'),
            $this->logoBlock(StarterAssets::LOGO_PLACEHOLDER, 'Hooli'),
        ]);
    }

    /**
     * Three pricing tiers as cards with a highlighted recommended plan. The
     * middle tier uses the elevated `card` preset + a primary CTA; the flanking
     * tiers use the subtle `surface` preset + outline CTAs for clear hierarchy.
     */
    private function pricingCardsNode(): array
    {
        $tier = function (string $name, string $price, string $period, array $features, string $cta, string $buttonStyle, string $surface): array {
            $blocks = [
                $this->headingBlock($name, 'h3'),
                $this->textBlock(trim($price.' '.$period)),
            ];
            foreach ($features as $feature) {
                $blocks[] = $this->textBlock($feature);
            }
            $blocks[] = $this->buttonBlock($cta, '#', $buttonStyle);

            return $this->columnBlock('33', $blocks, $surface);
        };

        return $this->sectionNode('columns', ['gap' => 'lg'], [
            $tier('Starter', '$0', '/ forever', ['1 project', 'Community support', 'Starter blocks'], 'Get started', 'outline', 'surface'),
            $tier('Pro', '$29', '/ month', ['Unlimited projects', 'Priority support', 'All blocks & patterns'], 'Start free trial', 'primary', 'card'),
            $tier('Scale', 'Custom', '', ['SSO & roles', 'Dedicated support', 'SLA & onboarding'], 'Contact sales', 'outline', 'surface'),
        ]);
    }

    // ---- Primitive node helpers ----------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function sectionNode(string $type, array $data, array $blocks = []): array
    {
        return ['type' => $type, 'id' => null, 'data' => $data, 'blocks' => $blocks];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function block(string $type, array $data): array
    {
        return ['type' => $type, 'data' => $data];
    }

    private function headingBlock(string $text, string $level): array
    {
        return $this->block('heading', ['text' => $text, 'level' => $level]);
    }

    private function textBlock(string $text): array
    {
        return $this->block('text', ['text' => $text]);
    }

    private function buttonBlock(string $label, string $url, string $style): array
    {
        return $this->block('button', ['label' => $label, 'url' => $url, 'style' => $style, 'size' => 'md']);
    }

    private function imageBlock(string $src, string $alt): array
    {
        return $this->block('image', ['src' => $src, 'alt' => $alt, 'caption' => '']);
    }

    private function quoteBlock(string $text, string $author, string $role): array
    {
        return $this->block('quote', ['text' => $text, 'author' => $author, 'role' => $role]);
    }

    private function logoBlock(string $src, string $alt): array
    {
        return $this->block('logo', ['src' => $src, 'alt' => $alt]);
    }

    /**
     * A `column` block carries its nested blocks under `data.blocks` (the frozen
     * container shape the renderer and import both understand).
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    private function columnBlock(string $width, array $blocks, ?string $surface = null): array
    {
        $data = ['width' => $width, 'blocks' => $blocks];
        if ($surface !== null) {
            $data['surface'] = $surface;
        }

        return $this->block('column', $data);
    }
}
