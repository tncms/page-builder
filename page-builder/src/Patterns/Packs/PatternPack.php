<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Patterns\Packs;

/**
 * Immutable metadata for a pattern pack (Phase 25.2, Part 3): a distributable folder
 * of patterns described by a `pack.json` at its root. Packs are how themes, plugins,
 * and the future marketplace ship patterns as a unit. Only `id` + `name` matter;
 * everything else degrades gracefully.
 */
final class PatternPack
{
    /**
     * @param  array<int, string>  $patternIds  Pattern ids the pack declares (informational).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version = '1.0.0',
        public readonly string $author = '',
        public readonly string $license = '',
        public readonly string $homepage = '',
        public readonly string $description = '',
        public readonly string $path = '',
        public readonly array $patternIds = [],
        public readonly string $website = '',
        public readonly bool $premium = false,
        public readonly float $price = 0.0,
        public readonly string $currency = '',
        public readonly InstallationMetadata $installation = new InstallationMetadata(),
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $path = ''): self
    {
        $id = trim((string) ($data['id'] ?? ''));
        $version = (string) ($data['version'] ?? '1.0.0');

        return new self(
            id: $id,
            name: trim((string) ($data['name'] ?? '')) ?: $id,
            version: $version,
            // Marketplace-ready: author may be a string or an object { name, url }.
            author: self::authorName($data['author'] ?? ''),
            license: (string) ($data['license'] ?? ''),
            homepage: (string) ($data['homepage'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            path: $path,
            patternIds: self::stringList($data['patterns'] ?? []),
            website: (string) ($data['website'] ?? ''),
            premium: (bool) ($data['premium'] ?? false),
            price: (float) ($data['price'] ?? 0),
            currency: (string) ($data['currency'] ?? ''),
            installation: InstallationMetadata::fromArray($data['installation'] ?? [], $version),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'version' => $this->version,
            'author' => $this->author,
            'license' => $this->license,
            'homepage' => $this->homepage,
            'website' => $this->website,
            'description' => $this->description,
            'premium' => $this->premium,
            'price' => $this->price,
            'currency' => $this->currency,
            'patterns' => $this->patternIds,
            'installation' => $this->installation->toArray(),
        ];
    }

    /**
     * @param  mixed  $author
     */
    private static function authorName(mixed $author): string
    {
        if (is_array($author)) {
            return trim((string) ($author['name'] ?? ''));
        }

        return trim((string) $author);
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($v): string => is_scalar($v) ? trim((string) $v) : '',
            $value,
        ), static fn (string $v): bool => $v !== ''));
    }
}
