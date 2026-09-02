<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Provenance;

/**
 * The ONE Page Builder-owned authority that reconciles trusted canonical provenance
 * into a layout at save time (PB-TREG-2 schema subphase). Templates, patterns, and
 * portable blocks all flow through here; there is no second stamping authority.
 *
 * Provenance means "this node/subtree was originally derived from this registered
 * resource" — NOT "this node is byte-identical to the current definition". So a node may
 * be freely edited (before OR after its first save) and keep its provenance, because
 * trust is bound to SERVER-owned node identity, never to editable content:
 *
 *   - existing persisted provenance is preserved for a node whose server identity (same
 *     id, same structural type) is unchanged, even after its editable data changed;
 *   - a genuinely new node gets provenance ONLY from a valid server-signed attestation
 *     ({@see MaterializationAttestation}) bound to that server-materialized node id +
 *     type, or from a direct trusted server import;
 *   - raw client `meta` is never trusted (callers pass the LayoutNormalizer output,
 *     which has already stripped all metadata);
 *   - a deleted node's provenance disappears naturally (it is not re-attached);
 *   - a duplicated / ambiguous / structurally-incompatible id never inherits provenance.
 *
 * The reconciled payload is the single canonical payload used for both the version
 * token and persistence — no second normalization runs after this.
 */
final class ProvenanceReconciler
{
    private const META = 'meta';

    private const PROVENANCE = 'provenance';

    /** DoS bounds enforced before any cryptographic work. */
    private const MAX_ATTESTATIONS = 64;

    private const MAX_AGGREGATE_BYTES = 262_144;

    public function __construct(
        private readonly MaterializationAttestation $attestation,
    ) {
    }

    /**
     * Reconcile a browser-relayed draft save.
     *
     * @param  array<string, mixed>|null  $current   the current persisted (meta-bearing) draft, or null
     * @param  array<string, mixed>       $incoming  the LayoutNormalizer output (NO metadata)
     * @param  array<int, string>         $attestations  opaque relayed tokens (transport only)
     * @return array<string, mixed>  the canonical reconciled payload (meta.provenance trusted only)
     *
     * @throws ProvenanceReconciliationException  atomically, on any invalid/moved/conflicting/excess token
     */
    public function reconcileDraft(?array $current, array $incoming, array $attestations, AttestationContext $context, ?int $now = null): array
    {
        $existing = $this->existingProvenance($current);
        [, $ambiguous] = $this->indexIds($incoming);

        // Server identity (id => type) of genuinely-new nodes; the ONLY nodes an
        // attestation may stamp. A client-declared id that is not a real new node here
        // can never receive provenance.
        $newNodes = $this->newNodeTypes($incoming, $existing, $ambiguous);

        $stampByNodeId = $this->resolveAttestations($attestations, $newNodes, $context, $now);

        return $this->stamp($incoming, $existing, $ambiguous, $stampByNodeId);
    }

    /**
     * Directly stamp trusted provenance for a server-side import of a single registered
     * resource (full-page template): no browser attestation is manufactured — the server
     * already holds the trusted resource. Every root section is marked as derived from it.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function stampDirect(array $payload, LayoutProvenance $provenance): array
    {
        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];

        $payload['sections'] = array_map(
            fn ($section): mixed => is_array($section)
                ? $this->withProvenance($this->stripMeta($section), $provenance)
                : $section,
            $sections,
        );

        return $payload;
    }

    // ---- existing provenance (preserve by server node identity) --------------

    /**
     * @param  array<string, mixed>|null  $current
     * @return array<string, array{type: string, provenance: LayoutProvenance}>
     */
    private function existingProvenance(?array $current): array
    {
        $map = [];
        foreach ($this->walk($current['sections'] ?? []) as $node) {
            $id = $node['id'] ?? null;
            $type = $node['type'] ?? null;
            if (! is_string($id) || $id === '' || ! is_string($type)) {
                continue;
            }
            $prov = LayoutProvenance::fromArray($node[self::META][self::PROVENANCE] ?? null);
            if ($prov !== null) {
                if (array_key_exists($id, $map)) {
                    $map[$id] = null; // duplicate id in persisted layout is ambiguous -> drop
                    continue;
                }
                $map[$id] = ['type' => $type, 'provenance' => $prov];
            }
        }

        return array_filter($map);
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array{0: array<string, int>, 1: array<string, bool>} [idCounts, ambiguousIds]
     */
    private function indexIds(array $incoming): array
    {
        $counts = [];
        foreach ($this->walk($incoming['sections'] ?? []) as $node) {
            $id = $node['id'] ?? null;
            if (is_string($id) && $id !== '') {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        $ambiguous = [];
        foreach ($counts as $id => $count) {
            if ($count > 1) {
                $ambiguous[$id] = true;
            }
        }

        return [$counts, $ambiguous];
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, array{type: string, provenance: LayoutProvenance}>  $existing
     * @param  array<string, bool>  $ambiguous
     * @return array<string, string>  new node id => structural type
     */
    private function newNodeTypes(array $incoming, array $existing, array $ambiguous): array
    {
        $new = [];
        foreach ($this->walk($incoming['sections'] ?? []) as $node) {
            $id = $node['id'] ?? null;
            $type = $node['type'] ?? null;
            if (! is_string($id) || $id === '' || ! is_string($type) || isset($ambiguous[$id]) || isset($existing[$id])) {
                continue;
            }
            $new[$id] = $type;
        }

        return $new;
    }

    // ---- attestation resolution (trusted server stamping) --------------------

    /**
     * @param  array<int, string>  $attestations
     * @param  array<string, string>  $newNodes  new node id => type
     * @return array<string, LayoutProvenance>  node id => trusted provenance
     *
     * @throws ProvenanceReconciliationException
     */
    private function resolveAttestations(array $attestations, array $newNodes, AttestationContext $context, ?int $now): array
    {
        if (count($attestations) > self::MAX_ATTESTATIONS) {
            throw new ProvenanceReconciliationException(ProvenanceReconciliationException::CODE_ATTESTATION_LIMIT);
        }

        $aggregate = 0;
        foreach ($attestations as $token) {
            $aggregate += is_string($token) ? strlen($token) : 0;
        }
        if ($aggregate > self::MAX_AGGREGATE_BYTES) {
            throw new ProvenanceReconciliationException(ProvenanceReconciliationException::CODE_ATTESTATION_LIMIT);
        }

        // 1. Open every chunk (signature + context + operational expiry) and group by
        //    materialization group id. A single forged/expired/wrong-context chunk rejects.
        $groups = [];
        foreach ($attestations as $token) {
            if (! is_string($token)) {
                throw new ProvenanceReconciliationException(ProvenanceReconciliationException::CODE_ATTESTATION_INVALID);
            }
            $opened = $this->attestation->open($token, $context, $now);
            if ($opened === null) {
                throw new ProvenanceReconciliationException(ProvenanceReconciliationException::CODE_ATTESTATION_INVALID);
            }
            $groups[$opened['groupId']][] = $opened;
        }

        // 2. Assemble each group atomically (complete chunk set + complete-manifest digest),
        //    then stamp its whole root set — partial application is impossible.
        $byNodeId = [];
        foreach ($groups as $chunks) {
            $group = $this->attestation->assembleGroup($chunks);
            if ($group === null) {
                throw new ProvenanceReconciliationException(ProvenanceReconciliationException::CODE_ATTESTATION_INVALID);
            }

            $prov = $group['provenance'];
            foreach ($group['roots'] as $root) {
                $nodeId = $root['nodeId'];
                // Every attested root must be a genuinely-new server node still present
                // with the exact structural type. One missing/moved/type-incompatible or
                // cross-group-overlapping root rejects the whole save.
                if (! isset($newNodes[$nodeId]) || $newNodes[$nodeId] !== $root['nodeType']) {
                    throw new ProvenanceReconciliationException(ProvenanceReconciliationException::CODE_ATTESTATION_UNMATCHED);
                }
                if (isset($byNodeId[$nodeId]) && ! $byNodeId[$nodeId]->equals($prov)) {
                    throw new ProvenanceReconciliationException(ProvenanceReconciliationException::CODE_ATTESTATION_CONFLICT);
                }
                $byNodeId[$nodeId] = $prov;
            }
        }

        return $byNodeId;
    }

    // ---- stamping ------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $incoming
     * @param  array<string, array{type: string, provenance: LayoutProvenance}>  $existing
     * @param  array<string, bool>  $ambiguous
     * @param  array<string, LayoutProvenance>  $stampByNodeId
     * @return array<string, mixed>
     */
    private function stamp(array $incoming, array $existing, array $ambiguous, array $stampByNodeId): array
    {
        $sections = is_array($incoming['sections'] ?? null) ? $incoming['sections'] : [];

        $incoming['sections'] = array_map(
            fn ($section): mixed => is_array($section)
                ? $this->stampNode($section, $existing, $ambiguous, $stampByNodeId)
                : $section,
            $sections,
        );

        return $incoming;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, array{type: string, provenance: LayoutProvenance}>  $existing
     * @param  array<string, bool>  $ambiguous
     * @param  array<string, LayoutProvenance>  $stampByNodeId
     * @return array<string, mixed>
     */
    private function stampNode(array $node, array $existing, array $ambiguous, array $stampByNodeId): array
    {
        $node = $this->stripMeta($node); // never trust an inbound meta key

        $id = $node['id'] ?? null;
        $type = $node['type'] ?? null;
        $provenance = null;

        if (is_string($id) && $id !== '' && ! isset($ambiguous[$id])) {
            if (isset($existing[$id]) && is_string($type) && $existing[$id]['type'] === $type) {
                $provenance = $existing[$id]['provenance']; // preserve by server identity
            } elseif (isset($stampByNodeId[$id])) {
                $provenance = $stampByNodeId[$id]; // stamp verified new materialization
            }
        }

        if (is_array($node['blocks'] ?? null)) {
            $node['blocks'] = array_map(
                fn ($child): mixed => is_array($child)
                    ? $this->stampNode($child, $existing, $ambiguous, $stampByNodeId)
                    : $child,
                $node['blocks'],
            );
        }
        if (is_array($node['data']['blocks'] ?? null)) {
            $node['data']['blocks'] = array_map(
                fn ($child): mixed => is_array($child)
                    ? $this->stampNode($child, $existing, $ambiguous, $stampByNodeId)
                    : $child,
                $node['data']['blocks'],
            );
        }

        return $provenance !== null ? $this->withProvenance($node, $provenance) : $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function stripMeta(array $node): array
    {
        unset($node[self::META]);

        return $node;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function withProvenance(array $node, LayoutProvenance $provenance): array
    {
        $node[self::META] = [self::PROVENANCE => $provenance->toArray()];

        return $node;
    }

    /**
     * Depth-first walk over sections + their (possibly nested) blocks, yielding each node.
     *
     * @param  mixed  $nodes
     * @return \Generator<int, array<string, mixed>>
     */
    private function walk(mixed $nodes): \Generator
    {
        if (! is_array($nodes)) {
            return;
        }

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            yield $node;

            if (is_array($node['blocks'] ?? null)) {
                yield from $this->walk($node['blocks']);
            }
            if (is_array($node['data']['blocks'] ?? null)) {
                yield from $this->walk($node['data']['blocks']);
            }
        }
    }
}
