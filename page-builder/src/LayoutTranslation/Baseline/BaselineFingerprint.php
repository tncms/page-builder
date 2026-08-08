<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\LayoutTranslation\Baseline;

use Plugins\PageBuilder\LayoutTranslation\Correspondence\NodeCorrespondence;
use Plugins\PageBuilder\Package\DesignPackageManifest;

/**
 * The content-addressed baseline fingerprint of a layout document (LT-4, decision
 * B — per frozen LT-0-ADR-001 D7).
 *
 * Layer: Translation (baseline). Computed over a canonical form with physical `id`s
 * AND correspondence metadata (`data['_origin']`) stripped, so a source and a
 * target derived from it hash IDENTICALLY (structure + content only). Order- and
 * id-independent. This is the authoritative provenance primitive; Core `cms.revision`
 * is deliberately not used (it prunes).
 */
final class BaselineFingerprint
{
    public const ALGO = 'sha256';

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function of(array $payload): string
    {
        return self::ALGO.'-'.hash(self::ALGO, DesignPackageManifest::canonicalJson(self::comparable($payload)));
    }

    /**
     * Strip physical ids and correspondence metadata so two comparable documents
     * hash equally.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function comparable(array $payload): array
    {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        $payload['sections'] = array_map(
            static fn ($section): array => self::stripNode(is_array($section) ? $section : [], true),
            $sections,
        );

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function stripNode(array $node, bool $isSection): array
    {
        unset($node['id']);

        if (is_array($node['data'] ?? null)) {
            unset($node['data'][NodeCorrespondence::ORIGIN_KEY]);
        }

        if ($isSection) {
            $blocks = is_array($node['blocks'] ?? null) ? $node['blocks'] : [];
            $node['blocks'] = array_map(
                static fn ($block): array => self::stripNode(is_array($block) ? $block : [], false),
                $blocks,
            );

            return $node;
        }

        if (is_array($node['data']['blocks'] ?? null)) {
            $node['data']['blocks'] = array_map(
                static fn ($block): array => self::stripNode(is_array($block) ? $block : [], false),
                $node['data']['blocks'],
            );
        }

        return $node;
    }
}
