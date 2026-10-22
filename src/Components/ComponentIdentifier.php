<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Components;

use Plugins\PageBuilder\Components\Exceptions\InvalidComponentIdentifierException;

/**
 * The canonical identity of a Page Builder component type (PB-XREG-1).
 *
 * Two shapes coexist in a stored layout's node `type`:
 *
 *   - BUILT-IN (bare): a single lowercase slug such as `hero`, `text`, `columns`.
 *     Reserved for the Page Builder first-party library; external providers may
 *     never register a bare id. Byte-compatible with every existing layout.
 *
 *   - EXTERNAL (namespaced): `<provider-slug>:<local-id>`, e.g.
 *     `travel-booking:tour-grid`. The provider segment is the canonical installed
 *     plugin slug (derived + verified server-side, never self-asserted); the local
 *     segment is the provider's own component id.
 *
 * This value object is the ONE grammar + bounds authority. It never resolves a
 * renderer, a class, a view, or a service — identity only. The persisted string
 * is the sole thing that travels in layout JSON; everything executable is resolved
 * server-side from trusted registration (see {@see ComponentProviderRegistry}).
 *
 * Per-segment grammar reuses the established {@see \Plugins\PageBuilder\Repository\Rid}
 * slug pattern (lowercase ASCII alphanumerics and single hyphens, no leading /
 * trailing / doubled hyphen). Length bounds are a Page-Builder-owned security
 * limit (Core imposes none on plugin slugs) chosen well above every real plugin
 * slug so no valid current slug is excluded.
 */
final class ComponentIdentifier
{
    /** Per-segment slug grammar (identical to Rid::SLUG — the existing authority). */
    public const SEGMENT_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /** Max characters for the provider segment and for the local segment. */
    public const MAX_SEGMENT_LENGTH = 64;

    /** Max characters for the whole identifier (`provider:local`). */
    public const MAX_TOTAL_LENGTH = 129;

    /** Namespace reserved for Page Builder itself; never available to external providers. */
    public const RESERVED_NAMESPACE = 'page-builder';

    /** Classification: a bare built-in id (no namespace). */
    public const KIND_BUILTIN = 'builtin';

    /** Classification: a well-formed external `provider:local` id. */
    public const KIND_EXTERNAL = 'external';

    /** Classification: anything that is neither a valid bare id nor a valid external id. */
    public const KIND_INVALID = 'invalid';

    private function __construct(
        public readonly string $namespace,
        public readonly string $local,
    ) {}

    /**
     * Parse a well-formed EXTERNAL identifier (`provider:local`), or throw.
     *
     * Does not treat the reserved `page-builder` namespace specially — grammar
     * only. Reserved-namespace ownership is a registration policy enforced by the
     * registry/validator, not an identity concern.
     *
     * @throws InvalidComponentIdentifierException
     */
    public static function parse(string $value): self
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > self::MAX_TOTAL_LENGTH) {
            throw InvalidComponentIdentifierException::forValue($value);
        }

        if (substr_count($value, ':') !== 1) {
            throw InvalidComponentIdentifierException::forValue($value);
        }

        [$namespace, $local] = explode(':', $value, 2);

        if (! self::isValidSegment($namespace) || ! self::isValidSegment($local)) {
            throw InvalidComponentIdentifierException::forValue($value);
        }

        return new self($namespace, $local);
    }

    /**
     * Classify a raw node `type` string without throwing:
     * {@see KIND_BUILTIN}, {@see KIND_EXTERNAL}, or {@see KIND_INVALID}.
     */
    public static function classify(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return self::KIND_INVALID;
        }

        if (! str_contains($value, ':')) {
            return self::isValidSegment($value) ? self::KIND_BUILTIN : self::KIND_INVALID;
        }

        return self::isValidExternal($value) ? self::KIND_EXTERNAL : self::KIND_INVALID;
    }

    /** True for a well-formed EXTERNAL `provider:local` identifier (grammar + bounds). */
    public static function isValidExternal(string $value): bool
    {
        try {
            self::parse($value);

            return true;
        } catch (InvalidComponentIdentifierException) {
            return false;
        }
    }

    /** True for a well-formed bare BUILT-IN identifier (a single slug segment). */
    public static function isBuiltin(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && ! str_contains($value, ':') && self::isValidSegment($value);
    }

    /** True when the provider segment is the Page Builder reserved namespace. */
    public function isReservedNamespace(): bool
    {
        return $this->namespace === self::RESERVED_NAMESPACE;
    }

    public function toString(): string
    {
        return $this->namespace.':'.$this->local;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /** A single slug segment within the per-segment length bound. */
    private static function isValidSegment(string $segment): bool
    {
        return $segment !== ''
            && strlen($segment) <= self::MAX_SEGMENT_LENGTH
            && preg_match(self::SEGMENT_PATTERN, $segment) === 1;
    }
}
