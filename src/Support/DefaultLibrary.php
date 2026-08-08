<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use Plugins\PageBuilder\Contracts\BlockDefinition;
use Plugins\PageBuilder\Contracts\SectionDefinition;
use Plugins\PageBuilder\Definitions\Blocks\AccordionItemBlock;
use Plugins\PageBuilder\Definitions\Blocks\ButtonBlock;
use Plugins\PageBuilder\Definitions\Blocks\ColumnBlock;
use Plugins\PageBuilder\Definitions\Blocks\ContainerBlock;
use Plugins\PageBuilder\Definitions\Blocks\HeadingBlock;
use Plugins\PageBuilder\Definitions\Blocks\IconBlock;
use Plugins\PageBuilder\Definitions\Blocks\ImageBlock;
use Plugins\PageBuilder\Definitions\Blocks\LogoBlock;
use Plugins\PageBuilder\Definitions\Blocks\QuoteBlock;
use Plugins\PageBuilder\Definitions\Blocks\TextBlock;
use Plugins\PageBuilder\Definitions\Sections\ColumnsSection;
use Plugins\PageBuilder\Definitions\Sections\CtaSection;
use Plugins\PageBuilder\Definitions\Sections\FaqSection;
use Plugins\PageBuilder\Definitions\Sections\GenericSection;
use Plugins\PageBuilder\Definitions\Sections\HeroSection;
use Plugins\PageBuilder\Definitions\Sections\ImageSection;
use Plugins\PageBuilder\Definitions\Sections\LogoStripSection;
use Plugins\PageBuilder\Definitions\Sections\TestimonialSection;
use Plugins\PageBuilder\Definitions\Sections\TextSection;
use Plugins\PageBuilder\Registries\BlockRegistry;
use Plugins\PageBuilder\Registries\SectionRegistry;

/**
 * The Page Builder's first-party section & block library (Phase 4, upgraded to
 * schema-driven object definitions in Phase 6).
 *
 * `sections()` / `blocks()` return the built-in {@see SectionDefinition} /
 * {@see BlockDefinition} objects keyed by type; `register()` feeds them to the
 * runtime registries, which normalize each to the plain array shape via
 * `toArray()`. Rendering still resolves views by convention, so the definitions
 * are purely metadata + editor schema — no HTML, no editor assets by default.
 */
final class DefaultLibrary
{
    /**
     * Register every built-in section and block into the runtime registries.
     */
    public function register(SectionRegistry $sections, BlockRegistry $blocks): void
    {
        foreach (self::sections() as $type => $definition) {
            $sections->register($type, $definition);
        }

        foreach (self::blocks() as $type => $definition) {
            $blocks->register($type, $definition);
        }
    }

    /**
     * Built-in section definitions, keyed by type.
     *
     * @return array<string, SectionDefinition>
     */
    public static function sections(): array
    {
        return self::keyByType([
            new GenericSection,
            new HeroSection,
            new TextSection,
            new ImageSection,
            new ColumnsSection,
            new CtaSection,
            new FaqSection,
            new TestimonialSection,
            new LogoStripSection,
        ]);
    }

    /**
     * Built-in block definitions, keyed by type.
     *
     * @return array<string, BlockDefinition>
     */
    public static function blocks(): array
    {
        return self::keyByType([
            new TextBlock,
            new HeadingBlock,
            new ButtonBlock,
            new ImageBlock,
            new IconBlock,
            new AccordionItemBlock,
            new ContainerBlock,
            new ColumnBlock,
            new LogoBlock,
            new QuoteBlock,
        ]);
    }

    /**
     * @template T of \Plugins\PageBuilder\Contracts\ComponentDefinition
     *
     * @param  array<int, T>  $definitions
     * @return array<string, T>
     */
    private static function keyByType(array $definitions): array
    {
        $keyed = [];
        foreach ($definitions as $definition) {
            $keyed[$definition->type()] = $definition;
        }

        return $keyed;
    }
}
