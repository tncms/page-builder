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
                'A polished corporate homepage: intro, capabilities, social proof and a closing call to action.', [
                    $this->heroNode('Build better products faster', 'A short supporting line that introduces what your company does and why it matters.', 'Get started', '#', 'surface'),
                    $this->featureGridNode([
                        ['Reliable', 'Built on proven, server-rendered foundations.'],
                        ['Flexible', 'Compose pages from reusable, editable blocks.'],
                        ['Secure', 'Output is escaped and sanitised by default.'],
                    ]),
                    $this->logoStripNode('Trusted by teams everywhere'),
                    $this->testimonialsNode(),
                    $this->ctaNode('Ready to get started?', 'Create your first page in minutes — no code required.', 'Get started'),
                ]),
            $this->page('agency-home', 'Agency Homepage', 'agency', ['agency', 'studio', 'creative'],
                'A bold agency landing page showcasing services, work and testimonials.', [
                    $this->heroNode('Design that works everywhere', 'We craft digital experiences that help ambitious teams stand out.', 'See our work', '#', 'dark'),
                    $this->featureCardsNode([
                        ['Strategy', 'Positioning and research that sets direction.', 'Learn more'],
                        ['Design', 'Interfaces and identities with real character.', 'Learn more'],
                        ['Build', 'Fast, accessible, production-ready delivery.', 'Learn more'],
                    ]),
                    $this->testimonialsNode(),
                    $this->logoStripNode('Selected clients'),
                    $this->ctaNode('Have a project in mind?', 'Tell us what you are building and we will help you ship it.', 'Start a project'),
                ]),
            $this->page('saas-landing', 'SaaS Landing', 'saas', ['saas', 'product', 'landing'],
                'A conversion-focused SaaS landing page with features, pricing and FAQ.', [
                    $this->heroNode('Everything you need to launch', 'One workspace to plan, build and ship — so your team moves faster together.', 'Start free', '#', 'plain', '/img/starters/saas-hero.png', 'Product screenshot'),
                    $this->featureGridNode([
                        ['Fast', 'Rendered on the server for instant loads.'],
                        ['Collaborative', 'Work together in a single shared space.'],
                        ['Extensible', 'Adapt it to your workflow with blocks.'],
                    ]),
                    $this->pricingNode(),
                    $this->testimonialsNode(),
                    $this->faqNode(),
                    $this->ctaNode('Start building today', 'Free to try. Upgrade whenever you are ready.', 'Start free'),
                ]),
            $this->page('portfolio', 'Portfolio', 'portfolio', ['portfolio', 'personal', 'showcase'],
                'A clean personal portfolio: introduction, selected work and contact.', [
                    $this->heroNode('Your work, beautifully presented', 'A short introduction about who you are and the kind of work you do.', 'View work', '#', 'plain'),
                    $this->featureCardsNode([
                        ['Project One', 'A short description of a selected project.', 'View case'],
                        ['Project Two', 'A short description of a selected project.', 'View case'],
                        ['Project Three', 'A short description of a selected project.', 'View case'],
                    ]),
                    $this->testimonialsNode(),
                    $this->ctaNode('Let’s work together', 'Available for select freelance and collaboration opportunities.', 'Get in touch'),
                ]),
            $this->page('blog-home', 'Blog Home', 'blog', ['blog', 'content', 'articles'],
                'A blog landing page featuring recent articles and a subscribe prompt.', [
                    $this->heroNode('Ideas worth sharing', 'Notes, tutorials and stories from the team — updated regularly.', 'Read the latest', '#', 'surface'),
                    $this->featureCardsNode([
                        ['Getting started', 'A friendly introduction to the basics.', 'Read article'],
                        ['Deep dive', 'A closer look at an advanced topic.', 'Read article'],
                        ['Behind the scenes', 'How we build and why it matters.', 'Read article'],
                    ]),
                    $this->ctaNode('Never miss a post', 'Subscribe to get new articles straight to your inbox.', 'Subscribe'),
                ]),
        ];
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
                $this->heroNode('Everything you need to launch', 'A product-led hero with a headline, subtitle, action and a supporting image.', 'Start free', '#', 'plain', '/img/starters/saas-hero.png', 'Product screenshot')),
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
                'A set of customer testimonials rendered as pull quotes.',
                $this->testimonialsNode()),
            $this->section('logo-strip', 'Logo Strip', 'social-proof', ['logos', 'brands', 'social-proof'],
                'A row of client or partner logos under a short heading.',
                $this->logoStripNode('Trusted by teams everywhere')),
            $this->section('cta-banner', 'CTA Banner', 'cta', ['cta', 'conversion', 'banner'],
                'A focused call-to-action band with a headline and button.',
                $this->ctaNode('Ready to get started?', 'Create your first page in minutes — no code required.', 'Get started')),
            $this->section('pricing', 'Pricing', 'conversion', ['pricing', 'plans', 'conversion'],
                'A three-tier pricing row built from columns.',
                $this->pricingNode()),
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
            preview: ['device' => 'desktop', 'sampleContent' => true],
            compatibility: ['schemaVersion' => '1'],
            distribution: ['entitlement' => 'free'],
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
            preview: ['device' => 'desktop', 'sampleContent' => true],
            compatibility: ['schemaVersion' => '1'],
            distribution: ['entitlement' => 'free'],
        );
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
            $this->logoBlock('/img/starters/logo-1.svg', 'Partner one'),
            $this->logoBlock('/img/starters/logo-2.svg', 'Partner two'),
            $this->logoBlock('/img/starters/logo-3.svg', 'Partner three'),
            $this->logoBlock('/img/starters/logo-4.svg', 'Partner four'),
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
    private function columnBlock(string $width, array $blocks): array
    {
        return $this->block('column', ['width' => $width, 'blocks' => $blocks]);
    }
}
