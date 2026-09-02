<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Provenance;

use Plugins\PageBuilder\Repository\DesignResource;
use Plugins\PageBuilder\Repository\Rid;

/**
 * The canonical, additive node-level provenance value object (PB-TREG-2 schema subphase).
 *
 * It records ONLY where an inserted subtree originally came from, as bounded scalar
 * metadata, never as a runtime rendering dependency. It is server-authority: it is
 * derived from a trusted registered {@see DesignResource}, never from client input, and
 * a forged/oversized/unknown shape fails closed to null rather than being interpreted.
 *
 * Persisted as `node.meta.provenance`; stripped from the render representation by the
 * existing {@see \Plugins\PageBuilder\Support\LayoutNormalizer} (which keeps only
 * type/id/data/blocks), so it can never become HTML or an executable sink.
 */
final class LayoutProvenance
{
    /** The registry contract/schema version this provenance shape belongs to. */
    public const REGISTRY_SCHEMA_VERSION = '1';

    /** The exact, ordered whitelist of persisted keys — nothing else may appear. */
    private const KEYS = [
        'provider_type',
        'provider_slug',
        'resource_kind',
        'resource_id',
        'definition_version',
        'registry_schema_version',
    ];

    private const SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /** A permissive-but-bounded version grammar (e.g. 1, 1.0.0, 1.2.0-beta.3). */
    private const VERSION = '/^[0-9A-Za-z][0-9A-Za-z.\-]{0,63}$/';

    private const MAX_LEN = 128;

    public function __construct(
        public readonly string $providerType,
        public readonly string $providerSlug,
        public readonly string $resourceKind,
        public readonly string $resourceId,
        public readonly string $definitionVersion,
        public readonly string $registrySchemaVersion,
    ) {
    }

    /**
     * Derive trusted provenance from a resolved registry resource. `$providerSlug`
     * is the server-authoritative provider identity (e.g. the active theme slug for a
     * theme resource), NEVER the resource's own untrusted vendor field.
     */
    public static function fromResource(DesignResource $resource, string $providerSlug): ?self
    {
        return self::fromArray([
            'provider_type' => $resource->source,
            'provider_slug' => $providerSlug,
            'resource_kind' => $resource->kind,
            'resource_id' => $resource->resourceId,
            'definition_version' => $resource->version !== '' ? $resource->version : '1.0.0',
            'registry_schema_version' => self::REGISTRY_SCHEMA_VERSION,
        ]);
    }

    /**
     * Validate an untrusted map into a bounded provenance object, or null when it is
     * absent/empty/invalid. Only the exact whitelist of scalar, length-bounded,
     * grammar-checked keys survives; any unknown key, non-scalar, oversize value, or
     * invalid kind fails the WHOLE object closed (never a partial trust).
     *
     * @param  mixed  $value
     */
    public static function fromArray(mixed $value): ?self
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        // Reject unknown keys outright (no silent stripping into a trusted object).
        foreach (array_keys($value) as $key) {
            if (! in_array($key, self::KEYS, true)) {
                return null;
            }
        }

        foreach (self::KEYS as $key) {
            $v = $value[$key] ?? null;
            if (! is_string($v) || $v === '' || strlen($v) > self::MAX_LEN) {
                return null;
            }
        }

        if (! in_array($value['resource_kind'], Rid::KINDS, true)) {
            return null;
        }
        if (! preg_match(self::SLUG, $value['provider_type'])
            || ! preg_match(self::SLUG, $value['provider_slug'])
            || ! preg_match(self::SLUG, $value['resource_id'])) {
            return null;
        }
        if (! preg_match(self::VERSION, $value['definition_version'])
            || ! preg_match(self::VERSION, $value['registry_schema_version'])) {
            return null;
        }

        return new self(
            (string) $value['provider_type'],
            (string) $value['provider_slug'],
            (string) $value['resource_kind'],
            (string) $value['resource_id'],
            (string) $value['definition_version'],
            (string) $value['registry_schema_version'],
        );
    }

    /**
     * The canonical, deterministically key-ordered persisted shape.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'provider_type' => $this->providerType,
            'provider_slug' => $this->providerSlug,
            'resource_kind' => $this->resourceKind,
            'resource_id' => $this->resourceId,
            'definition_version' => $this->definitionVersion,
            'registry_schema_version' => $this->registrySchemaVersion,
        ];
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
