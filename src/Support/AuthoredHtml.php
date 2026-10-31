<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;

/**
 * Render-time authority for author-entered HTML (PB-TEXT-AUTHORING-R1): the rich
 * `content` of the Visual Text block and the source of the Custom HTML block.
 *
 * Core owns HTML sanitation. Every value first goes through `cms_html()` (Core's
 * HtmlSanitizer: allow-listed tags/attributes, scheme-checked URLs, dangerous
 * subtrees and comments removed). Page Builder then only NARROWS that output to the
 * block's vocabulary; it never re-allows anything Core removed:
 *
 *   - TEXT   — paragraphs, line breaks, bold/italic/underline/strike, lists, links,
 *              blockquote, inline code and code blocks. Headings become paragraphs
 *              (heading rank stays with the Heading block), everything else is
 *              unwrapped so its text survives; only `a[href,title,target,rel]` keeps
 *              attributes.
 *   - CUSTOM — everything Core allows, except: an `<h1>` is renamed `<h2>` (the
 *              single page `<h1>` belongs to {@see HeadingRankResolver}), and an
 *              `<img>` must be same-origin (root-relative or this site's host) — no
 *              data: images and no automatic loading from other hosts.
 *
 * Links keep only `target="_blank"` (anything else is dropped) and always get
 * `rel="noopener noreferrer"` with it. The stored source is never rewritten: this
 * runs on every preview and public render, so both resolve the same bytes.
 *
 * Bounds: a value over {@see MAX_BYTES} bytes or nested deeper than {@see MAX_DEPTH}
 * elements renders as nothing (the save gate refuses it with a 422 first, see
 * {@see AuthoredHtmlLayoutValidator}). Rendering never throws for authored input.
 */
final class AuthoredHtml
{
    public const TEXT = 'text';

    public const CUSTOM = 'custom';

    public const MAX_BYTES = 65536;

    public const MAX_DEPTH = 32;

    /** @var list<string> */
    private const TEXT_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'a', 'blockquote', 'code', 'pre'];

    /** @var list<string> */
    private const HEADINGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    /** @var list<string> */
    private const LINK_ATTRS = ['href', 'title', 'target', 'rel'];

    /**
     * Sanitize author HTML for output under the given profile.
     */
    public static function render(string $html, string $profile): string
    {
        if (trim($html) === '' || self::violation($html) !== null) {
            return '';
        }

        $sanitized = cms_html($html);

        if ($sanitized === '') {
            return '';
        }

        return self::narrow($sanitized, $profile);
    }

    /**
     * Why a value exceeds the authoring bounds (`too_large` / `too_deep`), or null.
     * Depth counts open elements by a cheap tag scan, so a hostile value is refused
     * before any DOM is built for it.
     */
    public static function violation(string $html): ?string
    {
        if (strlen($html) > self::MAX_BYTES) {
            return 'too_large';
        }

        $depth = 0;
        $voids = ['br', 'hr', 'img', 'wbr', 'input', 'meta', 'link', 'source', 'area', 'col', 'embed', 'param', 'track', 'base'];

        if (preg_match_all('~<(/?)([a-zA-Z][a-zA-Z0-9-]*)[^>]*?(/?)>~', $html, $tags, PREG_SET_ORDER) === false) {
            return 'too_deep';
        }

        foreach ($tags as $tag) {
            $name = strtolower($tag[2]);

            if ($tag[1] === '/') {
                $depth = max(0, $depth - 1);
            } elseif ($tag[3] !== '/' && ! in_array($name, $voids, true)) {
                if (++$depth > self::MAX_DEPTH) {
                    return 'too_deep';
                }
            }
        }

        return null;
    }

    private static function narrow(string $html, string $profile): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div data-pb-root="1">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($dom))->query('//div[@data-pb-root="1"]')?->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        self::walk($root, $profile);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node, string $profile): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $element = self::narrowElement($child, $profile);

            if ($element !== null) {
                self::walk($element, $profile);
            }
        }
    }

    /** The element to keep walking into, or null when it was removed / unwrapped. */
    private static function narrowElement(DOMElement $element, string $profile): ?DOMElement
    {
        $tag = strtolower($element->tagName);

        if ($profile === self::TEXT) {
            // A heading, or a browser line wrapper (`<div>`) that holds only inline
            // content, is a paragraph in Text's vocabulary.
            if (in_array($tag, self::HEADINGS, true) || ($tag === 'div' && ! self::hasBlockChild($element))) {
                $element = self::rename($element, 'p');
                $tag = 'p';
            }

            if (! in_array($tag, self::TEXT_TAGS, true)) {
                self::walk($element, $profile);
                self::unwrap($element);

                return null;
            }

            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                if ($tag !== 'a' || ! in_array(strtolower($attribute->name), self::LINK_ATTRS, true)) {
                    $element->removeAttribute($attribute->name);
                }
            }
        } else {
            if ($tag === 'h1') {
                $element = self::rename($element, 'h2');
            }

            if ($tag === 'img' && ! self::sameOrigin($element->getAttribute('src'))) {
                $element->parentNode?->removeChild($element);

                return null;
            }
        }

        if ($tag === 'a') {
            self::normalizeLink($element);
        }

        return $element;
    }

    private static function normalizeLink(DOMElement $link): void
    {
        if ($link->hasAttribute('target') && strtolower(trim($link->getAttribute('target'))) !== '_blank') {
            $link->removeAttribute('target');
        }

        if ($link->hasAttribute('target')) {
            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noopener noreferrer');
        } else {
            $link->removeAttribute('rel');
        }
    }

    private static function sameOrigin(string $src): bool
    {
        $src = trim($src);

        if (str_starts_with($src, '/') && ! str_starts_with($src, '//')) {
            return true;
        }

        $host = parse_url($src, PHP_URL_HOST);
        $scheme = strtolower((string) parse_url($src, PHP_URL_SCHEME));
        $site = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && is_string($site) && $site !== ''
            && in_array($scheme, ['http', 'https'], true)
            && strcasecmp($host, $site) === 0;
    }

    private static function hasBlockChild(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['p', 'div', 'ul', 'ol', 'blockquote', 'pre', 'table', 'section', 'article', 'header', 'footer', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'details', 'aside', 'nav', 'main', 'hr'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function rename(DOMElement $element, string $tag): DOMElement
    {
        $renamed = $element->ownerDocument->createElement($tag);

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $renamed->setAttribute($attribute->name, $attribute->value);
        }

        while ($element->firstChild !== null) {
            $renamed->appendChild($element->firstChild);
        }

        $element->parentNode?->replaceChild($renamed, $element);

        return $renamed;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }
}
