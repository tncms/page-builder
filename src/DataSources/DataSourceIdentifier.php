<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\DataSources;

use Plugins\PageBuilder\Components\ComponentIdentifier;
use Plugins\PageBuilder\DataSources\Exceptions\InvalidDataSourceIdentifierException;

/**
 * The canonical identity of a Page Builder dynamic data source (PB-XREG-2).
 *
 * Always namespaced: `<provider-slug>:<local-source-id>`, e.g.
 * `ecommerce:latest-products`. There is no bare form — unlike components, Page
 * Builder ships no built-in sources, and any future first-party source must use
 * the reserved `page-builder` namespace through an internal provider.
 *
 * Grammar and bounds are deliberately IDENTICAL to {@see ComponentIdentifier}
 * (same per-segment slug pattern, same length limits, exactly one colon) so the
 * two public identity surfaces cannot drift. Identity only: this value object
 * never resolves a class, query, or service. The persisted string is the sole
 * thing that travels in layout JSON.
 */
final class DataSourceIdentifier
{
    public const SEGMENT_PATTERN = ComponentIdentifier::SEGMENT_PATTERN;

    public const MAX_SEGMENT_LENGTH = ComponentIdentifier::MAX_SEGMENT_LENGTH;

    public const MAX_TOTAL_LENGTH = ComponentIdentifier::MAX_TOTAL_LENGTH;

    /** Namespace reserved for Page Builder itself; never available to external providers. */
    public const RESERVED_NAMESPACE = ComponentIdentifier::RESERVED_NAMESPACE;

    private function __construct(
        public readonly string $namespace,
        public readonly string $local,
    ) {}

    /**
     * Parse a well-formed `provider:local` identifier, or throw.
     *
     * @throws InvalidDataSourceIdentifierException
     */
    public static function parse(string $value): self
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > self::MAX_TOTAL_LENGTH || substr_count($value, ':') !== 1) {
            throw InvalidDataSourceIdentifierException::forValue($value);
        }

        [$namespace, $local] = explode(':', $value, 2);

        if (! self::isValidSegment($namespace) || ! self::isValidSegment($local)) {
            throw InvalidDataSourceIdentifierException::forValue($value);
        }

        return new self($namespace, $local);
    }

    /** True for a well-formed `provider:local` identifier (grammar + bounds). */
    public static function isValid(string $value): bool
    {
        try {
            self::parse($value);

            return true;
        } catch (InvalidDataSourceIdentifierException) {
            return false;
        }
    }

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
    public static function isValidSegment(string $segment): bool
    {
        return $segment !== ''
            && strlen($segment) <= self::MAX_SEGMENT_LENGTH
            && preg_match(self::SEGMENT_PATTERN, $segment) === 1;
    }
}
