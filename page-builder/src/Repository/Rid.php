<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Repository;

use Plugins\PageBuilder\Repository\Exceptions\InvalidResourceIdentifierException;

/**
 * A Design Resource IDentifier: `<source>:<kind>:<resourceId>`.
 *
 * Immutable, self-describing, and globally unique. `kind` is a closed set;
 * `source` and `resourceId` are lowercase slugs. Examples:
 *   official:page:agency-home
 *   community:section:hero-modern
 *   premium:block:pricing-card
 *   builtin:section:hero
 */
final class Rid
{
    public const KIND_BLOCK = 'block';
    public const KIND_SECTION = 'section';
    public const KIND_PAGE = 'page';
    public const KIND_KIT = 'kit';

    public const KINDS = [self::KIND_BLOCK, self::KIND_SECTION, self::KIND_PAGE, self::KIND_KIT];

    private const SLUG = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    public function __construct(
        public readonly string $source,
        public readonly string $kind,
        public readonly string $resourceId,
    ) {
        if (! preg_match(self::SLUG, $source)) {
            throw InvalidResourceIdentifierException::forValue("{$source}:{$kind}:{$resourceId}");
        }
        if (! in_array($kind, self::KINDS, true)) {
            throw InvalidResourceIdentifierException::forValue("{$source}:{$kind}:{$resourceId}");
        }
        if (! preg_match(self::SLUG, $resourceId)) {
            throw InvalidResourceIdentifierException::forValue("{$source}:{$kind}:{$resourceId}");
        }
    }

    /**
     * @throws InvalidResourceIdentifierException
     */
    public static function parse(string $value): self
    {
        $parts = explode(':', $value);
        if (count($parts) !== 3) {
            throw InvalidResourceIdentifierException::forValue($value);
        }

        [$source, $kind, $resourceId] = $parts;

        return new self($source, $kind, $resourceId);
    }

    public static function isValid(string $value): bool
    {
        try {
            self::parse($value);

            return true;
        } catch (InvalidResourceIdentifierException) {
            return false;
        }
    }

    public function toString(): string
    {
        return "{$this->source}:{$this->kind}:{$this->resourceId}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
