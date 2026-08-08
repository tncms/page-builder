<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

/**
 * The License Contract for a Resource Manifest (Phase 26.50A, Rule 7).
 *
 * The foundation implements exactly ONE license type — `community` — and RESERVES
 * the `commercial`, `subscription` and `enterprise` types for later commercial
 * providers. This is METADATA ONLY: there is no pricing, no purchase, no
 * subscription and no licensing engine here (Rule 18). A future Marketplace EXTENDS
 * this by attaching purchase/subscription data to the same manifest — it never
 * changes the license contract itself (Rule 12).
 */
final class LicenseMetadata
{
    public const COMMUNITY = 'community';

    public const COMMERCIAL = 'commercial';   // reserved

    public const SUBSCRIPTION = 'subscription'; // reserved

    public const ENTERPRISE = 'enterprise';   // reserved

    public const TYPES = [self::COMMUNITY, self::COMMERCIAL, self::SUBSCRIPTION, self::ENTERPRISE];

    public function __construct(
        public readonly string $type = self::COMMUNITY,
        public readonly ?string $name = null,
        public readonly ?string $url = null,
        public readonly ?string $spdx = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: is_string($data['type'] ?? null) && $data['type'] !== '' ? $data['type'] : self::COMMUNITY,
            name: is_string($data['name'] ?? null) ? $data['name'] : null,
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
            spdx: is_string($data['spdx'] ?? null) ? $data['spdx'] : null,
        );
    }

    public function isCommunity(): bool
    {
        return $this->type === self::COMMUNITY;
    }

    public function isKnownType(): bool
    {
        return in_array($this->type, self::TYPES, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'url' => $this->url,
            'spdx' => $this->spdx,
        ];
    }
}
