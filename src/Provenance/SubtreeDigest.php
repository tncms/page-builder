<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Provenance;

/**
 * A stable content digest of an inserted subtree that is INVARIANT to node identity
 * (PB-TREG-2 schema subphase).
 *
 * A materialization attestation binds provenance to the CONTENT of the inserted
 * subtree, not to a node id — because the server regenerates fresh RIDs on insert, so
 * an id-bound digest would never match at save time. The digest recursively drops
 * every `id` and every `meta` key and canonically key-sorts the remainder, so:
 *
 *   - regenerating RIDs does NOT change the digest (verification survives insertion);
 *   - moving an attestation onto a subtree with DIFFERENT content DOES change the
 *     digest, so it fails verification (no cross-content forgery);
 *   - provenance/attestation data cannot influence its own digest (meta is excluded).
 */
final class SubtreeDigest
{
    /**
     * @param  array<string, mixed>  $node
     */
    public static function of(array $node): string
    {
        return hash('sha256', (string) json_encode(
            self::canonical(self::strip($node)),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * Recursively remove identity/metadata keys so the digest depends only on the
     * structural content (types, data, nesting).
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function strip(array $node): array
    {
        unset($node['id'], $node['meta']);

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = self::strip($value);
            }
        }

        return $node;
    }

    /**
     * Recursively key-sort so object-key order never changes the digest; numeric
     * (list) order is preserved.
     */
    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $canonical = array_map([self::class, 'canonical'], $value);
        ksort($canonical);

        return $canonical;
    }
}
