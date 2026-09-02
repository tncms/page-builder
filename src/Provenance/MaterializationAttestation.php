<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Provenance;

/**
 * Server-signed materialization attestations bound to the server-materialized node
 * IDENTITY, chunked into an atomic group (PB-TREG-2 schema subphase, Model A + Option A).
 *
 * One registered-resource insertion becomes ONE materialization group (a shared group
 * id) whose actual top-level roots are split into signed CHUNKS of at most
 * {@see MAX_ROOTS_PER_ATTESTATION} roots. Every chunk is bound to the group id, the
 * authorization context, the resource provenance, a purpose, an operational expiry, an
 * absolute renewal horizon, its chunk index, the total chunk count, and — critically —
 * the deterministic digest of the COMPLETE ordered root-set manifest. The complete
 * manifest binds every {nid,typ} pair, the exact total root count, and the exact chunk
 * count, so a client cannot omit/duplicate/reorder/replace/mix chunks or change the
 * declared total without failing verification.
 *
 * MAX_ROOTS_PER_ATTESTATION is a TRANSPORT/SECURITY bound (roots per signed token), not a
 * resource-schema limit: a valid resource with more roots is divided into more chunks and
 * never loses provenance capability. The canonical resource limit remains
 * ThemeResourceReader::MAX_NODES (500 total nodes).
 *
 * The browser is untrusted and transport-only; it relays opaque tokens and manages group
 * lifecycle (retain across edits, request narrowing/renewal), never a trust decision. The
 * attestation is NEVER persisted; only reconciled provenance is.
 */
final class MaterializationAttestation
{
    private const PURPOSE = 'pb-treg-2:materialization';

    /** Roots encoded per signed token (transport/security bound, NOT a schema limit). */
    public const MAX_ROOTS_PER_ATTESTATION = 32;

    /** Upper bound on one group's total roots, aligned to the canonical node authority. */
    public const MAX_GROUP_ROOTS = 500;

    public const DEFAULT_TTL = 3600;          // operational token lifetime

    public const DEFAULT_HORIZON = 28800;     // absolute renewal horizon (8h) from issuance

    private const MAX_TOKEN_BYTES = 8192;

    private const MAX_TYPE_LEN = 128;

    /** @param string $key raw signing key (>= 32 bytes) */
    public function __construct(private readonly string $key)
    {
        if (strlen($this->key) < 32) {
            throw new \InvalidArgumentException('Provenance signing key is too weak.');
        }
    }

    public static function fromAppKey(?string $appKey): self
    {
        if (! is_string($appKey) || $appKey === '') {
            throw new \InvalidArgumentException('Application key is not configured.');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);
            if ($decoded === false || $decoded === '') {
                throw new \InvalidArgumentException('Application key is not valid base64.');
            }
            $raw = $decoded;
        } else {
            $raw = $appKey;
        }

        return new self(hash_hkdf('sha256', $raw, 32, 'pb-treg-2:provenance-attestation:v1'));
    }

    /**
     * Mint the complete signed token GROUP for one materialization. The roots are the
     * actual server-generated top-level roots (`['id' => .., 'type' => ..]`), deterministically
     * ordered and chunked into groups of at most {@see MAX_ROOTS_PER_ATTESTATION}.
     *
     * @param  array<int, array{id: string, type: string}>  $roots
     * @return array<int, string>  one opaque token per chunk (ordered by chunk index)
     *
     * @throws \InvalidArgumentException on an empty/oversize/malformed/duplicate root set
     */
    public function issueGroup(
        LayoutProvenance $provenance,
        array $roots,
        AttestationContext $context,
        string $groupId,
        ?string $auditDigest = null,
        ?int $now = null,
        int $ttl = self::DEFAULT_TTL,
        int $horizon = self::DEFAULT_HORIZON,
    ): array {
        $now ??= time();
        $canonical = $this->canonicalRoots($roots);

        return $this->mintGroup($provenance, $canonical, $context, $groupId, $auditDigest, $now, $now, $now + max(1, $horizon), $ttl);
    }

    /**
     * Open ONE chunk token: verify signature, purpose, authorization context, and
     * operational expiry, returning the chunk claim WITHOUT any content comparison. Null
     * is a hard trust failure (forged/tampered/oversize/malformed/expired/wrong-context).
     *
     * @return array{provenance: LayoutProvenance, groupId: string, chunkIndex: int, chunkCount: int, roots: array<int, array{nodeId: string, nodeType: string}>, manifest: string, issuedAt: int, horizon: int, auditDigest: ?string}|null
     */
    public function open(string $token, AttestationContext $context, ?int $now = null): ?array
    {
        $now ??= time();

        if ($token === '' || strlen($token) > self::MAX_TOKEN_BYTES) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$body, $sig] = $parts;

        $expected = self::b64($this->sign($body));
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        $decoded = base64_decode(strtr($body, '-_', '+/'), true);
        $claim = $decoded === false ? null : json_decode($decoded, true);
        if (! is_array($claim)) {
            return null;
        }

        if (($claim['p'] ?? null) !== self::PURPOSE) {
            return null;
        }
        if (! is_array($claim['ctx'] ?? null) || $claim['ctx'] !== $context->toArray()) {
            return null;
        }
        if (! is_int($claim['exp'] ?? null) || $now >= $claim['exp']) {
            return null;
        }

        $gid = $claim['gid'] ?? null;
        $ci = $claim['ci'] ?? null;
        $cc = $claim['cc'] ?? null;
        $man = $claim['man'] ?? null;
        $iat = $claim['iat'] ?? null;
        $hor = $claim['hor'] ?? null;
        if (! is_string($gid) || $gid === '' || ! is_int($ci) || ! is_int($cc)
            || $cc < 1 || $ci < 0 || $ci >= $cc
            || ! is_string($man) || $man === '' || ! is_int($iat) || ! is_int($hor)) {
            return null;
        }

        $roots = $this->openRoots($claim['roots'] ?? null);
        if ($roots === null) {
            return null;
        }

        $provenance = LayoutProvenance::fromArray($claim['prov'] ?? null);
        if ($provenance === null) {
            return null;
        }

        $dig = $claim['dig'] ?? null;

        return [
            'provenance' => $provenance,
            'groupId' => $gid,
            'chunkIndex' => $ci,
            'chunkCount' => $cc,
            'roots' => $roots,
            'manifest' => $man,
            'issuedAt' => $iat,
            'horizon' => $hor,
            'auditDigest' => is_string($dig) ? $dig : null,
        ];
    }

    /**
     * Assemble + verify a COMPLETE group from its opened chunks (all chunks of one group
     * id). Returns the reconstructed group or null on any integrity failure: inconsistent
     * group metadata, a missing/duplicate/out-of-range chunk index, overlapping root ids,
     * a total/chunk-count mismatch, or a complete-manifest digest mismatch.
     *
     * @param  array<int, array<string, mixed>>  $chunks  results of {@see open()}
     * @return array{provenance: LayoutProvenance, roots: array<int, array{nodeId: string, nodeType: string}>, groupId: string, issuedAt: int, horizon: int, auditDigest: ?string}|null
     */
    public function assembleGroup(array $chunks): ?array
    {
        if ($chunks === []) {
            return null;
        }

        $first = $chunks[0];
        $gid = $first['groupId'];
        $cc = $first['chunkCount'];
        $man = $first['manifest'];
        $prov = $first['provenance'];
        $iat = $first['issuedAt'];
        $hor = $first['horizon'];
        $dig = $first['auditDigest'];

        $seenIndex = [];
        $roots = [];
        foreach ($chunks as $chunk) {
            // Consistent group metadata across every chunk.
            if ($chunk['groupId'] !== $gid || $chunk['chunkCount'] !== $cc || $chunk['manifest'] !== $man
                || $chunk['issuedAt'] !== $iat || $chunk['horizon'] !== $hor
                || (string) $chunk['auditDigest'] !== (string) $dig
                || ! $chunk['provenance']->equals($prov)) {
                return null;
            }
            $ci = $chunk['chunkIndex'];
            if (isset($seenIndex[$ci])) {
                return null; // duplicate chunk
            }
            $seenIndex[$ci] = true;
            foreach ($chunk['roots'] as $root) {
                if (isset($roots[$root['nodeId']])) {
                    return null; // overlapping root id across chunks
                }
                $roots[$root['nodeId']] = $root['nodeType'];
            }
        }

        // Exactly one token for every expected chunk index 0..cc-1.
        if (count($seenIndex) !== $cc) {
            return null;
        }

        // Reconstruct the complete ordered manifest and verify its digest.
        $ordered = $this->orderRoots($roots);
        if (! hash_equals($this->manifestDigest($ordered, $cc), $man)) {
            return null;
        }

        return [
            'provenance' => $prov,
            'roots' => $ordered,
            'groupId' => $gid,
            'issuedAt' => $iat,
            'horizon' => $hor,
            'auditDigest' => $dig,
        ];
    }

    /**
     * Renew a complete group: verify every chunk + the complete manifest, then reissue the
     * SAME roots/gid/provenance/issuance/horizon with a fresh operational expiry. Returns
     * the new token group, or null if any chunk/manifest check fails or the absolute
     * horizon has passed. Never resets materialization age or changes roots.
     *
     * @param  array<int, string>  $tokens
     * @return array<int, string>|null
     */
    public function renewGroup(array $tokens, AttestationContext $context, ?int $now = null, int $ttl = self::DEFAULT_TTL): ?array
    {
        $now ??= time();
        $group = $this->openAndAssemble($tokens, $context, $now);
        if ($group === null || $now >= $group['horizon']) {
            return null;
        }

        return $this->mintGroup(
            $group['provenance'],
            $this->toIdTypeList($group['roots']),
            $context,
            $group['groupId'],
            $group['auditDigest'],
            $group['issuedAt'],
            $now,
            $group['horizon'],
            $ttl,
        );
    }

    /**
     * Narrow/rebind a group to a STRICT SUBSET of its originally attested roots (some
     * pending roots were deleted before the first save). Verifies the complete original
     * group, accepts only retained original {nid,typ} pairs (no additions/replacements/
     * type changes/foreign roots), and rechunks the subset into a new complete group,
     * preserving gid/provenance/issuance/horizon/audit. Returns the new token group, the
     * string 'discard' when the retained set is empty (explicit discard), or null on any
     * failure / beyond the absolute horizon.
     *
     * @param  array<int, string>  $tokens
     * @param  array<int, string>  $retainedIds
     * @return array<int, string>|string|null
     */
    public function rebindGroup(array $tokens, array $retainedIds, AttestationContext $context, ?int $now = null, int $ttl = self::DEFAULT_TTL): array|string|null
    {
        $now ??= time();
        $group = $this->openAndAssemble($tokens, $context, $now);
        if ($group === null || $now >= $group['horizon']) {
            return null;
        }

        $original = [];
        foreach ($group['roots'] as $root) {
            $original[$root['nodeId']] = $root['nodeType'];
        }

        $retained = [];
        $seen = [];
        foreach ($retainedIds as $id) {
            if (! is_string($id) || ! isset($original[$id]) || isset($seen[$id])) {
                return null; // foreign / duplicate / non-original root id
            }
            $seen[$id] = true;
            $retained[] = ['id' => $id, 'type' => $original[$id]];
        }

        // Strict subset only (no additions); a full set is not a narrowing.
        if (count($retained) >= count($original)) {
            return null;
        }
        if ($retained === []) {
            return 'discard';
        }

        return $this->mintGroup(
            $group['provenance'],
            $this->canonicalRoots($retained),
            $context,
            $group['groupId'],
            $group['auditDigest'],
            $group['issuedAt'],
            $now,
            $group['horizon'],
            $ttl,
        );
    }

    // ---- internals -----------------------------------------------------------

    /**
     * @param  array<int, string>  $tokens
     * @return array{provenance: LayoutProvenance, roots: array<int, array{nodeId: string, nodeType: string}>, groupId: string, issuedAt: int, horizon: int, auditDigest: ?string}|null
     */
    private function openAndAssemble(array $tokens, AttestationContext $context, int $now): ?array
    {
        $chunks = [];
        foreach ($tokens as $token) {
            if (! is_string($token)) {
                return null;
            }
            $opened = $this->open($token, $context, $now);
            if ($opened === null) {
                return null;
            }
            $chunks[] = $opened;
        }

        return $this->assembleGroup($chunks);
    }

    /**
     * @param  array<int, array{id: string, type: string}>  $canonicalRoots  already validated + ordered
     * @return array<int, string>
     */
    private function mintGroup(
        LayoutProvenance $provenance,
        array $canonicalRoots,
        AttestationContext $context,
        string $groupId,
        ?string $auditDigest,
        int $issuedAt,
        int $now,
        int $horizonTs,
        int $ttl,
    ): array {
        $ordered = array_map(static fn (array $r): array => ['nodeId' => $r['id'], 'nodeType' => $r['type']], $canonicalRoots);
        $chunkSets = array_chunk($canonicalRoots, self::MAX_ROOTS_PER_ATTESTATION);
        $cc = count($chunkSets);
        $man = $this->manifestDigest($ordered, $cc);
        $exp = min($now + max(1, $ttl), $horizonTs);

        $tokens = [];
        foreach ($chunkSets as $ci => $chunk) {
            $claim = [
                'p' => self::PURPOSE,
                'prov' => $provenance->toArray(),
                'ctx' => $context->toArray(),
                'gid' => $groupId,
                'ci' => $ci,
                'cc' => $cc,
                'roots' => array_map(static fn (array $r): array => ['nid' => $r['id'], 'typ' => $r['type']], $chunk),
                'man' => $man,
                'iat' => $issuedAt,
                'exp' => $exp,
                'hor' => $horizonTs,
                'dig' => $auditDigest,
                'mid' => bin2hex(random_bytes(12)),
            ];
            $bodyEnc = self::b64(json_encode($claim, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $tokens[] = $bodyEnc.'.'.self::b64($this->sign($bodyEnc));
        }

        return $tokens;
    }

    /**
     * @param  array<int, array{id: string, type: string}>  $roots
     * @return array<int, array{id: string, type: string}>  validated, deduped, ordered by id
     */
    private function canonicalRoots(array $roots): array
    {
        if ($roots === [] || count($roots) > self::MAX_GROUP_ROOTS) {
            throw new \InvalidArgumentException('Invalid attested root set size.');
        }

        $byId = [];
        foreach ($roots as $root) {
            $id = $root['id'] ?? null;
            $type = $root['type'] ?? null;
            if (! is_string($id) || $id === '' || ! is_string($type) || $type === '' || strlen($type) > self::MAX_TYPE_LEN) {
                throw new \InvalidArgumentException('Malformed attested root.');
            }
            if (isset($byId[$id])) {
                throw new \InvalidArgumentException('Duplicate attested root id.');
            }
            $byId[$id] = ['id' => $id, 'type' => $type];
        }

        ksort($byId, SORT_STRING);

        return array_values($byId);
    }

    /**
     * @param  mixed  $roots
     * @return array<int, array{nodeId: string, nodeType: string}>|null
     */
    private function openRoots(mixed $roots): ?array
    {
        if (! is_array($roots) || $roots === [] || count($roots) > self::MAX_ROOTS_PER_ATTESTATION) {
            return null;
        }

        $out = [];
        $seen = [];
        foreach ($roots as $root) {
            if (! is_array($root)) {
                return null;
            }
            $nid = $root['nid'] ?? null;
            $typ = $root['typ'] ?? null;
            if (! is_string($nid) || $nid === '' || ! is_string($typ) || $typ === '' || strlen($typ) > self::MAX_TYPE_LEN) {
                return null;
            }
            if (isset($seen[$nid])) {
                return null;
            }
            $seen[$nid] = true;
            $out[] = ['nodeId' => $nid, 'nodeType' => $typ];
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $roots  nodeId => nodeType
     * @return array<int, array{nodeId: string, nodeType: string}>  ordered by id
     */
    private function orderRoots(array $roots): array
    {
        ksort($roots, SORT_STRING);

        $out = [];
        foreach ($roots as $nid => $typ) {
            $out[] = ['nodeId' => $nid, 'nodeType' => $typ];
        }

        return $out;
    }

    /**
     * @param  array<int, array{nodeId: string, nodeType: string}>  $roots
     * @return array<int, array{id: string, type: string}>
     */
    private function toIdTypeList(array $roots): array
    {
        return array_map(static fn (array $r): array => ['id' => $r['nodeId'], 'type' => $r['nodeType']], $roots);
    }

    /**
     * The complete ordered root-set manifest digest: binds every {nid,typ} pair, the
     * deterministic order, the exact total root count, and the exact chunk count.
     *
     * @param  array<int, array{nodeId: string, nodeType: string}>  $ordered
     */
    private function manifestDigest(array $ordered, int $chunkCount): string
    {
        $pairs = array_map(static fn (array $r): array => [$r['nodeId'], $r['nodeType']], $ordered);

        return hash('sha256', (string) json_encode([
            'total' => count($ordered),
            'chunks' => $chunkCount,
            'roots' => $pairs,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->key, true);
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
