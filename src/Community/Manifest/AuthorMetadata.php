<?php

declare(strict_types=1);

namespace Plugins\PageBuilder\Community\Manifest;

/**
 * Author metadata for a Resource Manifest (Phase 26.50A, Rule 10).
 *
 * The manifest OWNS author, website and profile. Verification, badges and follower
 * counts are RESERVED for a future identity/social layer — they are represented as
 * inert, platform-neutral fields here (never populated by this foundation) so the
 * format never has to change when that layer arrives. No user accounts exist yet
 * (Rule 18).
 */
final class AuthorMetadata
{
    /**
     * @param  array<int, string>  $badges  reserved — always empty in the foundation
     */
    public function __construct(
        public readonly string $name = '',
        public readonly ?string $website = null,
        public readonly ?string $profile = null,
        public readonly bool $verified = false,   // reserved
        public readonly array $badges = [],        // reserved
        public readonly ?int $followers = null,    // reserved
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: is_string($data['name'] ?? null) ? $data['name'] : '',
            website: is_string($data['website'] ?? null) ? $data['website'] : null,
            profile: is_string($data['profile'] ?? null) ? $data['profile'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'website' => $this->website,
            'profile' => $this->profile,
            // Reserved fields are serialized so the wire format is forward-stable.
            'verified' => $this->verified,
            'badges' => $this->badges,
            'followers' => $this->followers,
        ];
    }
}
